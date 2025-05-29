<?php 

namespace App\Service;

use App\Entity\Image;
use App\Entity\Offer;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class StripeService {

    private StripeClient $stripe;

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

    public function generatePaimentLink(Offer $offer): string
    {
        $lineItems = [
            [
                'price' => $offer->getStripePriceId(),
                'quantity' => 1,
            ],
        ];

        $session = $this->getStripe()->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => "{$this->parameterBag->get('app.frontend_url')}/offer/{$offer->getId()}/success?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$this->parameterBag->get('app.frontend_url')}/offer/{$offer->getId()}/cancel",
        ]);

        return $session->url;
    }

    public function deleteOffer(Offer $offer): void
    {
        $this->getStripe()->products->delete($offer->getStripeProductId());
    }



    private function getStripe(): StripeClient
    {
        return  $this->stripe ??= new StripeClient($_ENV['STRIPE_API_SECRET']);
    }
}
