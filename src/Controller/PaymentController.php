<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\TransactionStatus;
use App\Repository\OfferRepository;
use App\Repository\TransactionRepository;
use App\Service\QrCodeService;
use App\Service\ReservationService;
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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/v1/payments')]
#[OA\Tag(name: 'Paiements C2C')]
class PaymentController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private OfferRepository $offerRepository,
        private NormalizerInterface $serialize,
        private TransactionRepository $transactionRepository,
        private ReservationService $reservationService,
        private QrCodeService $qrCodeService
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
        description: 'URL de la page de paiement ou confirmation pour offre gratuite',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'checkoutUrl', type: 'string', example: 'https://checkout.stripe.com/...'),
                new OA\Property(property: 'message', type: 'string', example: 'Offre gratuite acquise avec succès'),
                new OA\Property(property: 'isFreeOffer', type: 'boolean', example: false)
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

        if ($offer->getPrice() == 0) {
            try {
                $transaction = new Transaction();
                $transaction->setOffer($offer);
                $transaction->setBuyer($buyer);
                $transaction->setSeller($seller);
                $transaction->setAmount(0);
                $transaction->setStatus(TransactionStatus::COMPLETED);
                $transaction->setCreatedAt(new \DateTimeImmutable());
                $transaction->setTransferredAt(new \DateTimeImmutable());

                $offer->setBuyer($buyer);
                $offer->setSoldAt(new \DateTime());
                
                $this->entityManager->persist($transaction);
                $this->entityManager->persist($offer);
                $this->entityManager->flush();
                
                return $this->json([
                    'success' => true,
                    'isFreeOffer' => true,
                    'message' => 'Cette offre gratuite est maintenant à vous!'
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'acquisition de l\'offre gratuite: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        try {
            $checkoutUrl = $this->stripeService->generatePaymentLink($offer, $buyer);
            
            return $this->json([
                'success' => true,
                'isFreeOffer' => false,
                'checkoutUrl' => $checkoutUrl
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la session de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/reserve/{id}', name: 'api_payment_reserve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de l\'offre à réserver',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Confirmation de réservation avec détails',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offre réservée avec succès'),
                new OA\Property(property: 'transaction', type: 'object')
            ]
        )
    )]
    public function reserveOffer(int $id): JsonResponse
    {
        $offer = $this->offerRepository->find($id);

        if (!$offer) {
            return $this->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($offer->getSoldAt() !== null || $offer->getBuyer() !== null) {
            return $this->json([
                'success' => false,
                'message' => 'Cette offre a déjà été réservée ou vendue'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        /** @var User $buyer */
        $buyer = $this->getUser();
        
        try {
            $transaction = $this->serialize->normalize(
                $this->reservationService->createReservation($offer, $buyer),
                null,
                ['groups' => ['transaction:read']]);
            
            return $this->json([
                'success' => true,
                'message' => 'Offre réservée avec succès! Le vendeur doit confirmer la réservation.',
                'transaction' => $transaction
            ], Response::HTTP_OK, ['groups' => ['transaction:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la réservation: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    
    #[Route('/reservations/{id}/confirm', name: 'api_reservation_confirm', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction / réservation à confirmer',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Confirmation de la réservation'
    )]
    public function confirmReservation(int $id): JsonResponse
    {
        /** @var User $seller */
        $seller = $this->getUser();
        $transaction = $this->transactionRepository->find($id);
        
        if (!$transaction) {
            return $this->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($transaction->getSeller()->getId() !== $seller->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à confirmer cette réservation'
            ], Response::HTTP_FORBIDDEN);
        }
        
        try {
            $transaction = $this->reservationService->confirmReservation($transaction, $seller);

            if ($transaction->isCompleted()) {
                return $this->json([
                    'success' => true,
                    'message' => 'Réservation confirmée et transaction terminée pour cette offre gratuite.',
                    'isFreeOffer' => true
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Réservation confirmée! L\'acheteur peut maintenant procéder au paiement.',
                'isFreeOffer' => false
            ], Response::HTTP_OK, ['groups' => ['transaction:read']]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    
    #[Route('/reservations/{id}/cancel', name: 'api_reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction / réservation à annuler',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function cancelReservation(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionRepository->find($id);
        
        if (!$transaction) {
            return $this->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($transaction->getBuyer()->getId() !== $user->getId() && $transaction->getSeller()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à annuler cette réservation'
            ], Response::HTTP_FORBIDDEN);
        }
        
        try {
            $this->reservationService->cancelReservation($transaction);
            
            return $this->json([
                'success' => true,
                'message' => 'Réservation annulée avec succès.'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
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
    
    #[Route('/transactions/{id}/generate-qr', name: 'api_transaction_generate_qr', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction pour laquelle générer un QR code',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'QR code généré avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    public function generateQrCode(int $id): JsonResponse
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

        if ($transaction->getSeller()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à générer un QR code pour cette transaction'
            ], Response::HTTP_FORBIDDEN);
        }
        
        try {
            $token = $this->qrCodeService->generateQrCode($transaction);
            
            return $this->json([
                'success' => true,
                'token' => $token,
                'expiresAt' => $transaction->getQrCodeExpiresAt()->format('Y-m-d\TH:i:s\Z')
            ], Response::HTTP_OK, ['groups' => ['transaction:read']]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du QR code: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    
    #[Route('/verify-qr', name: 'api_verify_qr', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\RequestBody(
        description: 'QR code token à vérifier',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'QR code vérifié avec succès'
    )]
    public function verifyQrCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        
        if (!$token) {
            return $this->json([
                'success' => false,
                'message' => 'Token QR code manquant'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $this->qrCodeService->verifyQrCode($token);
            
            return $this->json([
                'success' => true,
                'message' => 'QR code valide',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du QR code: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    
    #[Route('/transactions/{id}/complete-by-qr', name: 'api_transaction_complete_by_qr', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction à compléter',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction complétée avec succès'
    )]
    public function completeTransactionByQrCode(int $id): JsonResponse
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
        
        try {
            $this->qrCodeService->completeTransactionByQrCode($transaction);
            
            return $this->json([
                'success' => true,
                'message' => 'Transaction complétée avec succès!'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la validation de la transaction: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
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

        $existingTransaction = $this->transactionRepository->findOneBy([
            'offer' => $offerId,
            'status' => TransactionStatus::RESERVED,
            'buyer' => $buyerId
        ]);
        
        if ($existingTransaction) {
            $existingTransaction->setStatus(TransactionStatus::PENDING);
            $existingTransaction->setStripeSessionId($session->id);
            $existingTransaction->setStripePaymentIntentId($session->payment_intent);
            
            $this->entityManager->persist($existingTransaction);
        } else {
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

            $this->entityManager->persist($transaction);
        }
        
        $offer->setSoldAt(new \DateTime());
        $this->entityManager->persist($offer);
        $this->entityManager->flush();
    }
}
