<?php

namespace App\Service\Notification;

use App\Entity\Offer;
use App\Entity\User;
use App\Service\Notifier;

class OfferNotificationService
{
    public const TYPE_OFFER_EXPIRY_WARNING = 'offer_expiry_warning';

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
        $daysUntilExpiry = 0;
        if ($expiryDate) {
            $now = new \DateTime();
            $nowDate = new \DateTime($now->format('Y-m-d'));
            $expiryDateOnly = new \DateTime($expiryDate->format('Y-m-d'));

            $interval = $nowDate->diff($expiryDateOnly);
            $daysUntilExpiry = $interval->days;
        }

        $content = [
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'quantity' => $offer->getQuantity(),
            'days_until_expiry' => $daysUntilExpiry,
        ];

        return $this->notifier->emit($seller, self::TYPE_OFFER_EXPIRY_WARNING, $content);
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_OFFER_EXPIRY_WARNING,
        ];
    }
}
