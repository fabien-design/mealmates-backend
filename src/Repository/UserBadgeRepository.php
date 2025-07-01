<?php

namespace App\Repository;

use App\Entity\UserBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBadge>
 *
 * @method UserBadge|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserBadge|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserBadge[]    findAll()
 * @method UserBadge[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserBadgeRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, UserBadge::class);
  }

  public function findByUserAndType(int $userId, string $badgeType): array
  {
    return $this->createQueryBuilder('ub')
      ->join('ub.badge', 'b')
      ->andWhere('ub.user = :userId')
      ->andWhere('b.type = :type')
      ->setParameter('userId', $userId)
      ->setParameter('type', $badgeType)
      ->getQuery()
      ->getResult();
  }
}
