<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Trouve les messages d'une conversation, par ordre chronologique
     */
    public function findByConversation(Conversation $conversation, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les messages non lus dans toutes les conversations d'un utilisateur
     */
    public function countUnreadByUser(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.conversation', 'c')
            ->andWhere('c.seller = :user OR c.buyer = :user')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque tous les messages d'une conversation comme lus pour un utilisateur spécifique
     */
    public function markAsReadInConversation(Conversation $conversation, User $user): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':isReadTrue')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = :isReadFalse')
            ->setParameter('isReadTrue', true)
            ->setParameter('isReadFalse', false)
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les derniers messages pour un utilisateur depuis une timestamp donnée
     */
    public function findNewMessages(User $user, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.conversation', 'c')
            ->andWhere('(c.seller = :user OR c.buyer = :user)')
            ->andWhere('m.createdAt > :since')
            ->orderBy('m.createdAt', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest message in a conversation
     */
    public function findLatestInConversation(Conversation $conversation): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
