<?php

namespace App\Service;

use App\Entity\Offer;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\TransactionStatus;
use App\Repository\TransactionRepository;
use App\Service\Notification\TransactionNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ReservationService
{
    private const DEFAULT_RESERVATION_EXPIRY_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private TransactionNotificationService $notificationService,
        private readonly TransactionRepository $transactionRepository
    ) {
    }

    public function createReservation(Offer $offer, User $buyer): Transaction
    {
        if ($offer->getBuyer() !== null) {
            throw new \Exception('Cette offre est déjà réservée ou vendue.');
        }

        if ($buyer->getId() === $offer->getSeller()->getId()) {
            throw new \Exception('Vous ne pouvez pas réserver votre propre offre.');
        }

        $transaction = new Transaction();
        $transaction->setOffer($offer);
        $transaction->setBuyer($buyer);
        $transaction->setSeller($offer->getSeller());
        $transaction->setAmount($offer->getDynamicPrice() ?? $offer->getPrice());
        $transaction->setStatus(TransactionStatus::RESERVED);
        $transaction->setCreatedAt(new \DateTimeImmutable());
        $transaction->setReservedAt(new \DateTimeImmutable());

        $expiryHours = $this->parameterBag->get('app.reservation_expiry_hours') ?? self::DEFAULT_RESERVATION_EXPIRY_HOURS;
        $expiryDate = new \DateTimeImmutable("+{$expiryHours} hours");
        $transaction->setReservationExpiresAt($expiryDate);

        $offer->setBuyer($buyer);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $this->notificationService->notifySellerOfReservation($transaction);

        return $transaction;
    }

    public function confirmReservation(Transaction $transaction, User $seller): Transaction
    {
        if ($seller->getId() !== $transaction->getSeller()->getId()) {
            throw new \Exception('Vous n\'êtes pas autorisé à confirmer cette réservation.');
        }

        if (!$transaction->isReserved()) {
            throw new \Exception('Cette transaction n\'est pas en attente de confirmation.');
        }

        if ($transaction->isReservationExpired()) {
            throw new \Exception('Cette réservation a expiré.');
        }

        $transaction->setStatus(TransactionStatus::CONFIRMED);
        $transaction->setReservationExpiresAt(null);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->notificationService->notifyBuyerOfReservationConfirmation($transaction);

        return $transaction;
    }

    public function cancelReservation(Transaction $transaction): Transaction
    {
        if (!$transaction->isReserved() && !$transaction->isConfirmed()) {
            throw new \Exception('Cette transaction n\'est pas une réservation.');
        }

        $transaction->setStatus(TransactionStatus::FAILED);
        $transaction->setErrorMessage('Réservation annulée.');

        $offer = $transaction->getOffer();
        $offer->setBuyer(null);
        
        $this->entityManager->persist($transaction);
        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $this->notificationService->notifySellerOfReservationCancellation($transaction);

        return $transaction;
    }

    public function processExpiredReservations(): int
    {
        $expiredReservations = $this->transactionRepository->findExpiredReservations();
        
        $count = 0;
        foreach ($expiredReservations as $reservation) {
            $this->cancelReservation($reservation);
            $this->notificationService->notifyBuyerOfReservationExpiry($reservation);
            $count++;
        }
        
        return $count;
    }
}
