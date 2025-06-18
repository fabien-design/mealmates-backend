<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\TransactionStatus;
use App\Repository\OfferRepository;
use App\Repository\TransactionRepository;
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
#[OA\Tag(name: 'Paiements C2C')]
class PaymentController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private OfferRepository $offerRepository,
        private TransactionRepository $transactionRepository
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

        /** @var User $buyer */
        $buyer = $this->getUser();
        $seller = $offer->getSeller();

        if ($buyer->getId() === $seller->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas acheter votre propre offre'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $checkoutUrl = $this->stripeService->generatePaymentLink($offer, $buyer);
            
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
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Payload invalide', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }
        
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $this->processSuccessfulPayment($session);
                break;
                
            case 'payment_intent.succeeded':
                break;
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
            $session = $this->stripeService->getStripe()->checkout->sessions->retrieve($sessionId);
            
            if ($session->payment_status === 'paid') {
                if ($offer->getSoldAt() === null) {
                    $this->processSuccessfulPayment($session);
                }
                
                return $this->json([
                    'success' => true,
                    'message' => 'Paiement réussi ! L\'offre est maintenant à vous. Le vendeur recevra l\'argent une fois la transaction confirmée.'
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

    #[Route('/transactions/{id}/confirm', name: 'api_transaction_confirm', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction à confirmer',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction confirmée et argent transféré au vendeur'
    )]
    public function confirmTransaction(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionRepository->find($id);

        if (!$transaction) {
            return $this->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($transaction->getBuyer()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à confirmer cette transaction'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$transaction->isPending()) {
            return $this->json([
                'success' => false,
                'message' => 'Cette transaction ne peut plus être confirmée'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->stripeService->transferToSeller($transaction)) {
            return $this->json([
                'success' => true,
                'message' => 'Transaction confirmée ! Le vendeur a reçu le paiement.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du transfert au vendeur'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/transactions/{id}/refund', name: 'api_transaction_refund', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction à rembourser',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function refundTransaction(int $id, Request $request): JsonResponse
    {
        // @todo: Implementer cette fonctionnalité - si on a le temps
    
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionRepository->find($id);

        if (!$transaction) {
            return $this->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($transaction->getBuyer()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à rembourser cette transaction'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($transaction->isCompleted() || $transaction->isRefunded()) {
            return $this->json([
                'success' => false,
                'message' => 'Cette transaction ne peut plus être remboursée'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        if ($this->stripeService->refundTransaction($transaction, $reason)) {
            return $this->json([
                'success' => true,
                'message' => 'Transaction remboursée avec succès'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du remboursement'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function processSuccessfulPayment($session): void
    {
        $offerId = $session->metadata->offer_id;
        $buyerId = $session->metadata->buyer_id ?? null;
        $sellerId = $session->metadata->seller_id;
        
        $offer = $this->offerRepository->find($offerId);
        if (!$offer || !$buyerId) {
            return;
        }

        $transaction = new Transaction();
        $transaction->setOffer($offer);
        $transaction->setBuyer($this->entityManager->getReference(User::class, $buyerId));
        $transaction->setSeller($this->entityManager->getReference(User::class, $sellerId));
        $transaction->setAmount($session->amount_total / 100);
        $transaction->setStatus(TransactionStatus::PENDING);
        $transaction->setStripeSessionId($session->id);
        $transaction->setStripePaymentIntentId($session->payment_intent);
        $transaction->setCreatedAt(new \DateTimeImmutable());

        $offer->setBuyer($transaction->getBuyer());
        $offer->setSoldAt(new \DateTime());
        
        $this->entityManager->persist($transaction);
        $this->entityManager->persist($offer);
        $this->entityManager->flush();
    }
}
