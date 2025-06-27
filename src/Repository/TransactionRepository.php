<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Enums\TransactionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findExpiredReservations(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.reservationExpiresAt IS NOT NULL')
            ->andWhere('t.reservationExpiresAt < :now')
            ->setParameter('status', TransactionStatus::RESERVED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    public function findExpiredQrCodes(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.qrCodeExpiresAt IS NOT NULL')
            ->andWhere('t.qrCodeExpiresAt < :now')
            ->setParameter('status', TransactionStatus::RESERVED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    public function findTransactionsByUser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.buyer = :userId')
            ->orWhere('t.seller = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveReservationsForSeller(int $sellerId): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('t')
            ->where('t.seller = :sellerId')
            ->andWhere('t.status = :status')
            ->andWhere('t.reservationExpiresAt > :now')
            ->setParameter('sellerId', $sellerId)
            ->setParameter('status', TransactionStatus::RESERVED)
            ->setParameter('now', $now)
            ->orderBy('t.reservedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTransactionsByTransferDate(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('t')
        ->where('t.status = :status')
        ->andWhere('t.transferredAt <= :endDate')
        ->andWhere('t.transferredAt >= :startDate')
        ->setParameter('status', TransactionStatus::COMPLETED)
        ->setParameter('endDate', $endDate)
        ->setParameter('startDate', $startDate)
        ->getQuery()
        ->getResult();
    }
}
