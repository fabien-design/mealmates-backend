<?php

namespace App\Service\Notification;

use App\Entity\Offer;
use App\Entity\User;
use App\Service\Notification\Notifier;

class OfferNotificationService
{
    public const TYPE_OFFER_EXPIRY_WARNING = 'offer_expiry_warning';
    public const TYPE_OFFER_PURCHASE_REQUEST = 'offer_purchase_request';
    public const TYPE_OFFER_SOLD = 'offer_sold';

    public function __construct(
        private readonly Notifier $notifier
    ) {
    }

    public function notifyOfferExpiryWarning(Offer $offer): bool
    {
        $seller = $offer->getSeller();
        
        if (!$seller) {
            return false;
        }

        $expiryDate = $offer->getExpiryDate();
        $daysUntilExpiry = $expiryDate ? $expiryDate->diff(new \DateTime())->days : 0;

        $content = [
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'quantity' => $offer->getQuantity(),
            'days_until_expiry' => $daysUntilExpiry,
        ];

        return $this->notifier->emit($seller, self::TYPE_OFFER_EXPIRY_WARNING, $content);
    }

    public function notifyOfferPurchaseRequest(Offer $offer, User $buyer): bool
    {
        $seller = $offer->getSeller();
        
        if (!$seller || $seller === $buyer) {
            return false;
        }

        $content = [
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'quantity' => $offer->getQuantity(),
            'buyer_id' => $buyer->getId(),
            'buyer_fullname' => $buyer->getFullName(),
        ];

        return $this->notifier->emit($seller, self::TYPE_OFFER_PURCHASE_REQUEST, $content);
    }

    public function notifyOfferSold(Offer $offer, User $buyer): bool
    {
        $seller = $offer->getSeller();
        
        if (!$seller || $seller === $buyer) {
            return false;
        }

        $content = [
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'quantity' => $offer->getQuantity(),
            'price' => $offer->getPrice(),
            'buyer_id' => $buyer->getId(),
            'buyer_fullname' => $buyer->getFullName(),
            'sale_date' => ($offer->getSoldAt())->format('Y-m-d H:i:s'),
        ];

        return $this->notifier->emit($seller, self::TYPE_OFFER_SOLD, $content);
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_OFFER_EXPIRY_WARNING,
            self::TYPE_OFFER_PURCHASE_REQUEST,
            self::TYPE_OFFER_SOLD,
        ];
    }
}
