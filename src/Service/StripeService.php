<?php 

namespace App\Service;

use App\Entity\Image;
use App\Entity\Offer;
use App\Entity\User;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class StripeService {

    private StripeClient $stripe;
    private const PLATFORM_FEE_PERCENTAGE = 10; // on fait raquer 10%

    public function __construct(private ParameterBagInterface $parameterBag)
    {
    }

    public function createOffer(Offer $offer): \Stripe\Product
    {
        return $this->getStripe()->products->create([
            'name' => $offer->getName(),
            'description' => $offer->getDescription(),
            'active' => $offer->isActive(),
            'images' => $offer->getImages()->map(fn(Image $image) => "{$this->parameterBag->get('app.frontend_url')}/{$image->getName()}")->toArray(),
        ]);
    }

    public function createPrice(Offer $offer): \Stripe\Price
    {
        // Convertir le prix en centimes (1€ = 100 centimes)
        $priceInCents = (int)($offer->getPrice() * 100);
        
        return $this->getStripe()->prices->create([
            'unit_amount' => $priceInCents,
            'currency' => 'eur',
            'product' => $offer->getStripeProductId(),    
        ]);
    }

    public function updateOffer(Offer $offer): \Stripe\Product
    {
        return $this->getStripe()->products->update(
            $offer->getStripeProductId(),
            [
                'name' => $offer->getName(),
                'description' => $offer->getDescription(),
                'active' => $offer->isActive(),
                'images' => $offer->getImages()->map(fn(Image $image) => "{$this->parameterBag->get('app.frontend_url')}/{$image->getName()}")->toArray(),
            ]
        );
    }

    public function updatePrice(Offer $offer): \Stripe\Price
    {
        // Convertir le prix en centimes (1€ = 100 centimes)
        $priceInCents = (int)($offer->getPrice() * 100);
        
        return $this->getStripe()->prices->update(
            $offer->getStripePriceId(),
            [
                'unit_amount' => $priceInCents,
                'currency' => 'eur',
            ]
        );
    }

    /**
     * Génère un lien de paiement avec transfert au vendeur via Stripe Connect
     */
    public function generatePaimentLink(Offer $offer, ?User $buyer = null): string
    {
        $seller = $offer->getSeller();
        $sellerStripeAccountId = $seller->getStripeConnectId();
        
        if (!$sellerStripeAccountId) {
            throw new \Exception('Le vendeur n\'a pas de compte Stripe Connect lié');
        }

        $priceInCents = (int)($offer->getPrice() * 100);
        $applicationFeeAmount = (int)($priceInCents * self::PLATFORM_FEE_PERCENTAGE / 100);

        $lineItems = [
            [
                'price' => $offer->getStripePriceId(),
                'quantity' => 1,
            ],
        ];
        
        $metadata = [
            'offer_id' => $offer->getId(),
            'seller_id' => $seller->getId(),
        ];
        
        // Ajouter l'ID de l'acheteur s'il est fourni
        if ($buyer) {
            $metadata['buyer_id'] = $buyer->getId();
        }

        $session = $this->getStripe()->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'payment_intent_data' => [
                'application_fee_amount' => $applicationFeeAmount,
                'transfer_data' => [
                    'destination' => $sellerStripeAccountId,
                ],
            ],
            'metadata' => $metadata,
            'success_url' => "{$this->parameterBag->get('app.frontend_url')}/offer/{$offer->getId()}/success?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$this->parameterBag->get('app.frontend_url')}/offer/{$offer->getId()}/cancel",
        ]);

        return $session->url;
    }

    public function deleteOffer(Offer $offer): void
    {
        $this->getStripe()->products->delete($offer->getStripeProductId());
    }

    public function createConnectAccount(User $user): \Stripe\Account
    {
        $account = $this->getStripe()->accounts->create([
            'type' => 'express',
            'country' => 'FR',
            'email' => $user->getEmail(),
            'capabilities' => [
                'transfers' => ['requested' => true],
                'card_payments' => ['requested' => true],
            ],
            'business_type' => 'individual',
            'business_profile' => [
                'name' => $user->getFullName(),
                'product_description' => 'Vente de produits alimentaires sur MealMates',
            ],
        ]);
        
        return $account;
    }

    public function generateOnboardingLink(string $accountId): string
    {
        $accountLink = $this->getStripe()->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => "{$this->parameterBag->get('app.frontend_url')}/profile/stripe/refresh",
            'return_url' => "{$this->parameterBag->get('app.frontend_url')}/profile/stripe/complete",
            'type' => 'account_onboarding',
        ]);
        
        return $accountLink->url;
    }

    public function isConnectAccountReady(string $accountId): bool
    {
        $account = $this->getStripe()->accounts->retrieve($accountId);
        return $account->charges_enabled && $account->payouts_enabled;
    }

    public function createPaymentToSeller(Offer $offer, User $buyer): \Stripe\PaymentIntent
    {
        $seller = $offer->getSeller();
        $sellerStripeAccountId = $seller->getStripeConnectId();
        
        if (!$sellerStripeAccountId) {
            throw new \Exception('Le vendeur n\'a pas de compte Stripe Connect lié');
        }

        $priceInCents = (int)($offer->getPrice() * 100);
        $applicationFeeAmount = (int)($priceInCents * self::PLATFORM_FEE_PERCENTAGE / 100);

        return $this->getStripe()->paymentIntents->create([
            'amount' => $priceInCents,
            'currency' => 'eur',
            'application_fee_amount' => $applicationFeeAmount,
            'transfer_data' => [
                'destination' => $sellerStripeAccountId,
            ],
            'metadata' => [
                'offer_id' => $offer->getId(),
                'buyer_id' => $buyer->getId(),
                'seller_id' => $seller->getId(),
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Génère un lien vers le dashboard Express du vendeur
     */
    public function generateDashboardLink(string $accountId): string
    {
        $loginLink = $this->getStripe()->accounts->createLoginLink($accountId);
        return $loginLink->url;
    }
    
    public function getStripe(): StripeClient
    {
        return  $this->stripe ??= new StripeClient($_ENV['STRIPE_API_SECRET']);
    }
}
