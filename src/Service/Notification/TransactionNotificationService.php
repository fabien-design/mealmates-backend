<?php

namespace App\Service\Notification;

use App\Entity\Transaction;
use App\Repository\ConversationRepository;
use App\Service\Notifier;

class TransactionNotificationService
{
    public const TYPE_RESERVATION_REQUEST = 'reservation_request';
    public const TYPE_RESERVATION_CONFIRMED = 'reservation_confirmed';
    public const TYPE_RESERVATION_CANCELLED = 'reservation_cancelled';
    public const TYPE_RESERVATION_EXPIRED = 'reservation_expired';
    public const TYPE_TRANSACTION_COMPLETED = 'transaction_completed';
    public const TYPE_TRANSACTION_PAID = 'transaction_paid';
    public const TYPE_TRANSACTION_QR_VALIDATED = 'transaction_qr_validated';
    public const TYPE_REVIEW_REMINDER = 'review_reminder';

    public function __construct(
        private readonly Notifier $notifier,
        private readonly ConversationRepository $conversationRepository
    ) {
    }

    public function notifySellerOfReservation(Transaction $transaction): bool
    {
        $seller = $transaction->getSeller();
        $buyer = $transaction->getBuyer();
        $offer = $transaction->getOffer();
        
        if (!$seller || !$buyer || $seller === $buyer) {
            return false;
        }

        $expiryDate = $transaction->getReservationExpiresAt();
        $conversation = $this->conversationRepository->findByOfferAndUsers(
            $offer->getId(),
            $buyer->getId(),
            $seller->getId()
        );

        $content = [
            'transaction_id' => $transaction->getId(),
            'conversation_id' => $conversation ? $conversation->getId() : null,
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'buyer_id' => $buyer->getId(),
            'buyer_fullname' => $buyer->getFullName(),
            'reservation_expires_at' => $expiryDate ? $expiryDate->format('Y-m-d H:i:s') : null,
        ];

        return $this->notifier->emit($seller, self::TYPE_RESERVATION_REQUEST, $content);
    }

    public function notifyBuyerOfReservationConfirmation(Transaction $transaction): bool
    {
        $buyer = $transaction->getBuyer();
        $seller = $transaction->getSeller();
        $offer = $transaction->getOffer();
        
        if (!$buyer || !$seller || $buyer === $seller) {
            return false;
        }

        $content = [
            'transaction_id' => $transaction->getId(),
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'seller_id' => $seller->getId(),
            'seller_fullname' => $seller->getFullName(),
            'is_free_offer' => $transaction->isFree(),
        ];

        return $this->notifier->emit($buyer, self::TYPE_RESERVATION_CONFIRMED, $content);
    }

    public function notifySellerBuyerOfReservationCancellation(Transaction $transaction): bool
    {
        $seller = $transaction->getSeller();
        $buyer = $transaction->getBuyer();
        $offer = $transaction->getOffer();
        
        if (!$seller || !$buyer || $seller === $buyer) {
            return false;
        }

        $content = [
            'transaction_id' => $transaction->getId(),
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'buyer_id' => $buyer->getId(),
            'buyer_fullname' => $buyer->getFullName(),
        ];

        $buyerResult = $this->notifier->emit($buyer, self::TYPE_RESERVATION_CANCELLED, $content);
        $content['is_seller'] = true;
        $sellerResult = $this->notifier->emit($seller, self::TYPE_RESERVATION_CANCELLED, $content);

        return $buyerResult && $sellerResult;
    }

    public function notifyBuyerOfReservationExpiry(Transaction $transaction): bool
    {
        $buyer = $transaction->getBuyer();
        $offer = $transaction->getOffer();
        
        if (!$buyer) {
            return false;
        }

        $content = [
            'transaction_id' => $transaction->getId(),
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
        ];

        return $this->notifier->emit($buyer, self::TYPE_RESERVATION_EXPIRED, $content);
    }

    public function notifyTransactionCompleted(Transaction $transaction): bool
    {
        $buyer = $transaction->getBuyer();
        $seller = $transaction->getSeller();
        $offer = $transaction->getOffer();
        
        if (!$buyer || !$seller || $buyer === $seller) {
            return false;
        }

        $conversation = $this->conversationRepository->findByOfferAndUsers(
            $offer->getId(),
            $buyer->getId(),
            $seller->getId()
        );

        $content = [
            'transaction_id' => $transaction->getId(),
            'conversation_id' => $conversation ? $conversation->getId() : null,
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'amount' => $transaction->getAmountWithFees(),
            'completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $sellerResult = $this->notifier->emit($seller, self::TYPE_TRANSACTION_COMPLETED, $content);

        $buyerResult = $this->notifier->emit($buyer, self::TYPE_TRANSACTION_COMPLETED, $content);
        
        return $sellerResult && $buyerResult;
    }

    public function notifySellerBuyerOfTransactionPaid(Transaction $transaction): bool
    {
        $buyer = $transaction->getBuyer();
        $seller = $transaction->getSeller();
        $offer = $transaction->getOffer();
        
        if (!$buyer || !$seller || $buyer === $seller) {
            return false;
        }

        $conversation = $this->conversationRepository->findByOfferAndUsers(
            $offer->getId(),
            $buyer->getId(),
            $seller->getId()
        );

        $content = [
            'transaction_id' => $transaction->getId(),
            'conversation_id' => $conversation ? $conversation->getId() : null,
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
        ];

        
        $buyerResult = $this->notifier->emit($buyer, self::TYPE_TRANSACTION_PAID, $content);
        $content['buyer_fullname'] = $buyer->getFullName();
        $content['is_seller'] = true;
        $sellerResult = $this->notifier->emit($seller, self::TYPE_TRANSACTION_PAID, $content);

        
        
        return $sellerResult && $buyerResult;
    }

    public function notifyQrCodeValidation(Transaction $transaction): bool
    {
        $seller = $transaction->getSeller();
        $buyer = $transaction->getBuyer();
        $offer = $transaction->getOffer();
        
        if (!$seller || !$buyer || $seller === $buyer) {
            return false;
        }

        $conversation = $this->conversationRepository->findByOfferAndUsers(
            $offer->getId(),
            $buyer->getId(),
            $seller->getId()
        );

        $content = [
            'transaction_id' => $transaction->getId(),
            'conversation_id' => $conversation ? $conversation->getId() : null,
            'offer_id' => $offer->getId(),
            'offer_name' => $offer->getName(),
            'buyer_id' => $buyer->getId(),
            'buyer_fullname' => $buyer->getFullName(),
            'validated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        return $this->notifier->emit($seller, self::TYPE_TRANSACTION_QR_VALIDATED, $content);
    }
    
    public function notifyReviewReminder(Transaction $transaction): bool
    {
        $buyer = $transaction->getBuyer();
        $seller = $transaction->getSeller();
        $offer = $transaction->getOffer();
        
        if (!$buyer || !$seller || $buyer === $seller) {
            return false;
        }
        
        $buyerReview = $transaction->getBuyerReview();
        $sellerReview = $transaction->getSellerReview();
        
        $result = true;

        if (!$buyerReview) {
            $buyerContent = [
                'transaction_id' => $transaction->getId(),
                'offer_id' => $offer->getId(),
                'offer_name' => $offer->getName(),
                'seller_id' => $seller->getId(),
                'seller_firstname' => $seller->getFirstName(),
                'seller_lastname' => $seller->getLastNameInitial(),
                'completed_at' => $transaction->getTransferredAt() ? $transaction->getTransferredAt()->format('Y-m-d H:i:s') : null,
            ];
            
            $result = $result && $this->notifier->emit($buyer, self::TYPE_REVIEW_REMINDER, $buyerContent);
        }

        if (!$sellerReview) {
            $sellerContent = [
                'transaction_id' => $transaction->getId(),
                'offer_id' => $offer->getId(),
                'offer_name' => $offer->getName(),
                'buyer_id' => $buyer->getId(),
                'buyer_firstname' => $buyer->getFirstName(),
                'buyer_lastname' => $buyer->getLastNameInitial(),
                'completed_at' => $transaction->getTransferredAt() ? $transaction->getTransferredAt()->format('Y-m-d H:i:s') : null,
            ];
            
            $result = $result && $this->notifier->emit($seller, self::TYPE_REVIEW_REMINDER, $sellerContent);
        }
        
        return $result;
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_RESERVATION_REQUEST,
            self::TYPE_RESERVATION_CONFIRMED,
            self::TYPE_RESERVATION_CANCELLED,
            self::TYPE_RESERVATION_EXPIRED,
            self::TYPE_TRANSACTION_COMPLETED,
            self::TYPE_TRANSACTION_PAID,
            self::TYPE_TRANSACTION_QR_VALIDATED,
            self::TYPE_REVIEW_REMINDER,
        ];
    }
}
