<?php 

namespace App\Service;

use App\Entity\Offer;
use App\Entity\User;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Transaction;
use App\Enums\TransactionStatus;
use Psr\Log\LoggerInterface;

final class StripeService {

    private StripeClient $stripe;
    private const PLATFORM_FEE_PERCENTAGE = 10; // on fait raquer 10%

    public function __construct(
        private ParameterBagInterface $parameterBag,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function generatePaymentLink(Offer $offer, ?User $buyer = null): string
    {
        $priceInCents = (int)($offer->getPrice() * 100);
        
        $lineItems = [
            [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $offer->getName(),
                        'description' => $offer->getDescription(),
                    ],
                    'unit_amount' => $priceInCents,
                ],
                'quantity' => 1,
            ],
        ];
        
        $metadata = [
            'offer_id' => $offer->getId(),
            'seller_id' => $offer->getSeller()->getId(),
            'type' => 'c2c_purchase',
        ];
        
        if ($buyer) {
            $metadata['buyer_id'] = $buyer->getId();
        }

        $session = $this->getStripe()->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'metadata' => $metadata,
            'success_url' => "{$this->parameterBag->get('app.backend_url')}/api/v1/payments/success/{$offer->getId()}?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$this->parameterBag->get('app.frontend_url')}/offer/{$offer->getId()}/cancel",
            'payment_intent_data' => [
                'description' => "Achat MealMates: {$offer->getName()}",
                'metadata' => $metadata,
            ]
        ]);

        return $session->url;
    }

    public function processPayment(string $sessionId): ?Transaction
    {
        $session = $this->getStripe()->checkout->sessions->retrieve($sessionId);
        
        if ($session->payment_status !== 'paid') {
            return null;
        }

        $offerId = $session->metadata->offer_id;
        $sellerId = $session->metadata->seller_id;
        $buyerId = $session->metadata->buyer_id ?? null;

        $transaction = new Transaction();
        $transaction->setStripeSessionId($sessionId);
        $transaction->setStripePaymentIntentId($session->payment_intent);
        $transaction->setAmount($session->amount_total / 100);
        $transaction->setStatus(TransactionStatus::PENDING);
        $transaction->setCreatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function transferToSeller(Transaction $transaction): bool
    {
        try {
            $seller = $transaction->getOffer()->getSeller();

            if (!$seller->getStripeAccountId()) {
                throw new \Exception('Le vendeur n\'a pas configuré son compte de réception');
            }

            $amountInCents = (int)($transaction->getAmount() * 100);
            $platformFee = (int)($amountInCents * self::PLATFORM_FEE_PERCENTAGE / 100);
            $sellerAmount = $amountInCents - $platformFee;

            $transfer = $this->getStripe()->transfers->create([
                'amount' => $sellerAmount,
                'currency' => 'eur',
                'destination' => $seller->getStripeAccountId(),
                'description' => "Vente MealMates: {$transaction->getOffer()->getName()}",
                'metadata' => [
                    'transaction_id' => $transaction->getId(),
                    'offer_id' => $transaction->getOffer()->getId(),
                ]
            ]);

            $transaction->setStatus(TransactionStatus::COMPLETED);
            $transaction->setTransferredAt(new \DateTimeImmutable());
            $transaction->setStripeTransferId($transfer->id);
            
            $this->entityManager->flush();

            return true;

        } catch (\Exception $e) {
            $this->logger->critical('Erreur lors du transfert Stripe vers le vendeur', [
                'transaction_id' => $transaction->getId(),
                'error' => $e->getMessage(),
            ]);
            $transaction->setStatus(TransactionStatus::FAILED);
            $transaction->setErrorMessage($e->getMessage());
            $this->entityManager->flush();
            
            return false;
        }
    }

    public function createSellerAccount(User $user): string
    {
        $accountLink = $this->getStripe()->accountLinks->create([
            'account' => $this->getOrCreateExpressAccount($user),
            'refresh_url' => "{$this->parameterBag->get('app.frontend_url')}/profile/banking/refresh",
            'return_url' => "{$this->parameterBag->get('app.frontend_url')}/profile/banking/complete",
            'type' => 'account_onboarding',
        ]);
        
        return $accountLink->url;
    }

    public function deleteSellerAccount(User $user): bool
    {
        if (!$user->getStripeAccountId()) {
            return false;
        }

        try {
            $this->getStripe()->accounts->delete($user->getStripeAccountId());
            $user->setStripeAccountId(null);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            $this->logger->critical('Erreur lors de la suppression du compte Stripe', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getOrCreateExpressAccount(User $user): string
    {
        if ($user->getStripeAccountId()) {
            return $user->getStripeAccountId();
        }

        $account = $this->getStripe()->accounts->create([
            'type' => 'express',
            'country' => 'FR',
            'email' => $user->getEmail(),
            'business_type' => 'individual',
            'individual' => [
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
            ],
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'business_profile' => [
                'product_description' => 'Vente occasionnelle de produits alimentaires',
                'mcc' => '5499', // Code pour vente de produits alimentaires divers
            ],
        ]);

        $user->setStripeAccountId($account->id);
        $this->entityManager->flush();
        
        return $account->id;
    }

    public function refundTransaction(Transaction $transaction, ?string $reason = null): bool
    {
        try {
            $refund = $this->getStripe()->refunds->create([
                'payment_intent' => $transaction->getStripePaymentIntentId(),
                'reason' => $reason ?? 'requested_by_customer',
                'metadata' => [
                    'transaction_id' => $transaction->getId(),
                ]
            ]);

            $transaction->setStatus(TransactionStatus::REFUNDED);
            $transaction->setRefundedAt(new \DateTimeImmutable());
            $transaction->setStripeRefundId($refund->id);
            
            $this->entityManager->flush();

            return true;

        } catch (\Exception $e) {
            $this->logger->critical('Erreur lors du remboursement Stripe', [
                'transaction_id' => $transaction->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getStripe(): StripeClient
    {
        return  $this->stripe ??= new StripeClient($_ENV['STRIPE_API_SECRET']);
    }
}
