<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Trouve toutes les conversations d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.seller = :user OR c.buyer = :user')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une conversation entre un acheteur et un vendeur à propos d'une offre
     */
    public function findByOfferAndUsers(int $offerId, int $buyerId, int $sellerId): ?Conversation
    {
        $qb = $this->createQueryBuilder('c');

        return $qb
            ->andWhere('c.offer = :offerId')
            ->andWhere('c.buyer = :buyerId')
            ->andWhere('c.seller = :sellerId')
            ->setParameter('offerId', $offerId)
            ->setParameter('buyerId', $buyerId)
            ->setParameter('sellerId', $sellerId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
