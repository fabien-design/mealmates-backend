<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OfferRepository;
use App\Repository\UserRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

#[Route('/api/v1/payments')]
#[OA\Tag(name: 'Paiements')]
class PaymentController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private OfferRepository $offerRepository,
        private UserRepository $userRepository
    ) {
        Stripe::setApiKey($_ENV['STRIPE_API_SECRET']);
    }

    #[Route('/checkout/{id}', name: 'api_payment_checkout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de l\'offre à acheter',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'URL de la page de paiement',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'checkoutUrl', type: 'string', example: 'https://checkout.stripe.com/...')
            ]
        )
    )]
    public function createCheckoutSession(int $id): JsonResponse
    {
        $offer = $this->offerRepository->find($id);

        if (!$offer) {
            return $this->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($offer->getSoldAt() !== null) {
            return $this->json([
                'success' => false,
                'message' => 'Cette offre a déjà été vendue'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Vérifier que le vendeur a un compte Stripe Connect actif
        $seller = $offer->getSeller();
        if (!$seller || !$seller->getStripeConnectId()) {
            return $this->json([
                'success' => false,
                'message' => 'Le vendeur n\'est pas configuré pour recevoir des paiements'
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $buyer */
        $buyer = $this->getUser();

        if ($buyer->getId() === $seller->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas acheter votre propre offre'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            /** @var User $user */
            $user = $this->getUser();
            $checkoutUrl = $this->stripeService->generatePaimentLink($offer, $user);
            
            return $this->json([
                'success' => true,
                'checkoutUrl' => $checkoutUrl
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la session de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/webhook', name: 'api_payment_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;
        
        if (!$endpointSecret) {
            return new Response('Webhook secret non configuré', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        try {
            // Vérifier la signature du webhook
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Payload invalide', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }
        
        // Gérer les différents types d'événements
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                
                // Extraire les métadonnées
                if (isset($session->metadata->offer_id) && isset($session->metadata->buyer_id)) {
                    $offerId = $session->metadata->offer_id;
                    $buyerId = $session->metadata->buyer_id;
                    
                    // Traitement de l'achat
                    $this->processSuccessfulPayment($offerId, $buyerId);
                }
                break;
                
            case 'payment_intent.succeeded':
                // Gérer les intents réussis si nécessaire
                break;
                
            // Vous pouvez ajouter d'autres types d'événements selon vos besoins
        }
        
        return new Response('Webhook reçu et traité avec succès', Response::HTTP_OK);
    }
    
    #[Route('/success/{id}', name: 'api_payment_success', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de l\'offre achetée',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'session_id',
        description: 'ID de la session de paiement Stripe',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Confirmation du paiement',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Paiement réussi ! L\'offre est maintenant à vous.')
            ]
        )
    )]
    public function confirmPayment(int $id, Request $request): JsonResponse
    {
        $sessionId = $request->query->get('session_id');
        if (!$sessionId) {
            return $this->json([
                'success' => false,
                'message' => 'Paramètre session_id manquant'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $offer = $this->offerRepository->find($id);
        if (!$offer) {
            return $this->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }
        
        try {
            // Vérifier l'état de la session de paiement
            $session = $this->stripeService->getStripe()->checkout->sessions->retrieve($sessionId);
            
            if ($session->payment_status === 'paid') {
                /** @var User $buyer */
                $buyer = $this->getUser();
                // Si le webhook n'a pas encore été traité, marquer l'offre comme vendue
                if ($offer->getSoldAt() === null) {
                    $this->processSuccessfulPayment($id, $buyer->getId());
                }
                
                return $this->json([
                    'success' => true,
                    'message' => 'Paiement réussi ! L\'offre est maintenant à vous.'
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => 'Le paiement n\'est pas encore validé.'
                ]);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Traite un paiement réussi en marquant l'offre comme vendue
     */
    private function processSuccessfulPayment(int $offerId, int $buyerId): void
    {
        $offer = $this->offerRepository->find($offerId);
        $buyer = $this->userRepository->find($buyerId);
        
        if (!$offer || !$buyer) {
            return;
        }
        
        // Mettre à jour l'offre
        $offer->setBuyer($buyer);
        $offer->setSoldAt(new \DateTime());
        
        $this->entityManager->persist($offer);
        $this->entityManager->flush();
        
        // Ici vous pourriez également:
        // - Envoyer une notification au vendeur
        // - Envoyer un email de confirmation à l'acheteur
        // - Créer une entrée dans l'historique des transactions
    }
}