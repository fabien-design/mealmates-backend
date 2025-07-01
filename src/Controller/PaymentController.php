<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\TransactionStatus;
use App\Repository\OfferRepository;
use App\Repository\TransactionRepository;
use App\Service\Notification\TransactionNotificationService;
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
    private const STRIPE_MINIMUM_AMOUNT = 0.50;

    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private OfferRepository $offerRepository,
        private NormalizerInterface $serialize,
        private TransactionRepository $transactionRepository,
        private ReservationService $reservationService,
        private QrCodeService $qrCodeService,
        private readonly TransactionNotificationService $notificationService
    ) {
        Stripe::setApiKey($_ENV['STRIPE_API_SECRET']);
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

        if ($buyer->getId() === $offer->getSeller()->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas réserver votre propre offre'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $transaction = $this->reservationService->createReservation($offer, $buyer);

            return $this->json([
                'success' => true,
                'message' => 'Offre réservée avec succès! Le vendeur doit confirmer la réservation.',
                'transaction' => $transaction
            ], Response::HTTP_OK, [], ['groups' => ['transaction:read']]);
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
            $isFreeOffer = $transaction->isFree();

            return $this->json([
                'success' => true,
                'message' => $isFreeOffer
                    ? 'Réservation confirmée! Vous pouvez maintenant convenir d\'un rendez-vous via la messagerie.'
                    : 'Réservation confirmée! L\'acheteur doit maintenant effectuer le paiement.',
                'isFreeOffer' => $isFreeOffer,
                'needsPayment' => !$isFreeOffer,
                'transaction' => $transaction
            ], Response::HTTP_OK, [], ['groups' => ['transaction:read']]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/transactions/{id}/pay', name: 'api_transaction_pay', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction à payer',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'URL de redirection vers Stripe',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'checkoutUrl', type: 'string', example: 'https://checkout.stripe.com/...'),
            ]
        )
    )]
    public function payForTransaction(int $id, Request $request): JsonResponse
    {
        $transaction = $this->transactionRepository->find($id);
        $redirectURI = $request->query->get('redirectURI', null);

        if (!$transaction) {
            return $this->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $buyer */
        $buyer = $this->getUser();

        if ($transaction->getBuyer()->getId() !== $buyer->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à payer cette transaction'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$transaction->isConfirmed()) {
            return $this->json([
                'success' => false,
                'message' => 'Cette transaction n\'est pas confirmée par le vendeur'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($transaction->isFree()) {
            return $this->json([
                'success' => false,
                'message' => 'Cette offre est gratuite, aucun paiement requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($transaction->getAmount() < self::STRIPE_MINIMUM_AMOUNT) {
            return $this->json([
                'success' => false,
                'message' => 'Le montant minimum pour un paiement est de ' . self::STRIPE_MINIMUM_AMOUNT . '€'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $checkoutUrl = $this->stripeService->generatePaymentLinkForTransaction($transaction, $redirectURI);

            return $this->json($checkoutUrl, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    public function generateQrCode(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        /** @var Transaction|null $transaction */
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
                'message' => 'Seul l\'acheteur peut générer le QR code'
            ], Response::HTTP_FORBIDDEN);
        }

        $isReadyForQr = false;
        $errorMessage = '';

        if ($transaction->isFree()) {
            $isReadyForQr = $transaction->isPending(); 
            if (!$transaction->isPending()) {
                $errorMessage = 'Le vendeur doit d\'abord confirmer la réservation';
            }
        } else {
            $isReadyForQr = $transaction->isPending();
            if ($transaction->isConfirmed() && !$transaction->isPending()) {
                $errorMessage = 'Le paiement doit être effectué avant de générer le QR code';
            } else if (!$transaction->isConfirmed()) {
                $errorMessage = 'Le vendeur doit d\'abord confirmer la réservation';
            }
        }

        if (!$isReadyForQr) {
            return $this->json([
                'success' => false,
                'message' => $errorMessage
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $token = $this->qrCodeService->generateQrCode($transaction);

            return $this->json([
                'success' => true,
                'token' => $token,
                'expiresAt' => $transaction->getQrCodeExpiresAt()->format('Y-m-d\TH:i:s\Z'),
                'message' => 'QR Code généré. Présentez-le au vendeur lors de la rencontre.'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du QR code: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/transactions/{id}/validate-qr', name: 'api_transaction_validate_qr', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la transaction à valider',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'token',
        description: 'Token QR code à valider',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction validée avec succès'
    )]
    public function validateQrCode(Transaction $transaction, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$transaction) {
            return $this->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($transaction->getSeller()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Seul le vendeur peut valider le QR code'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $token = $request->query->get('token');

        if (!$token) {
            return $this->json([
                'success' => false,
                'message' => 'Token QR code manquant'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            if ($transaction->getQrCodeToken() !== $token) {
                throw new \Exception('QR Code invalide');
            }

            if ($transaction->isQrCodeExpired()) {
                throw new \Exception('QR Code expiré');
            }

            $this->qrCodeService->completeTransactionByQrCode($transaction);

            if (!$transaction->isFree() && $transaction->isPending()) {
                $this->stripeService->transferToSeller($transaction);
            }

            return $this->json([
                'success' => true,
                'message' => 'Transaction finalisée avec succès! La remise est confirmée.'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage()
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

        if ($transaction->getSeller()->getId() !== $user->getId()) {
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
        $transactionId = $session->metadata->transaction_id;

        if (!$transactionId) {
            return;
        }

        $transaction = $this->transactionRepository->find($transactionId);

        if (!$transaction || !$transaction->isConfirmed()) {
            return;
        }

        $transaction->setStatus(TransactionStatus::PENDING);
        $transaction->setStripeSessionId($session->id);
        $transaction->setStripePaymentIntentId($session->payment_intent);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->notificationService->notifySellerBuyerOfTransactionPaid($transaction);
    }
}
