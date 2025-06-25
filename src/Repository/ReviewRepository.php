<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\User;
use App\Enums\ReviewStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Review::class);
  }

  /**
   * @return Review[] Returns an array of approved Review objects for a user
   */
  public function findApprovedReviewsForUser(User $user): array
  {
    return $this->createQueryBuilder('r')
      ->andWhere('r.reviewed = :user')
      ->andWhere('r.status = :status')
      ->setParameter('user', $user)
      ->setParameter('status', ReviewStatus::APPROVED)
      ->orderBy('r.createdAt', 'DESC')
      ->getQuery()
      ->getResult();
  }

  public function findAverageRatingsForUser(User $user): array
  {
    $result = $this->createQueryBuilder('r')
      ->select('AVG(r.productQualityRating) as avgProductQuality')
      ->addSelect('AVG(r.appointmentRespectRating) as avgAppointmentRespect')
      ->addSelect('AVG(r.friendlinessRating) as avgFriendliness')
      ->andWhere('r.reviewed = :user')
      ->andWhere('r.status IN :status')
      ->setParameter('user', $user)
      ->setParameter('status', [ReviewStatus::APPROVED, ReviewStatus::PENDING])
      ->getQuery()
      ->getOneOrNullResult();

    return [
      'avgProductQuality' => $result['avgProductQuality'] ?? 0,
      'avgAppointmentRespect' => $result['avgAppointmentRespect'] ?? 0,
      'avgFriendliness' => $result['avgFriendliness'] ?? 0,
      'avgOverall' => (($result['avgProductQuality'] ?? 0) + ($result['avgAppointmentRespect'] ?? 0) + ($result['avgFriendliness'] ?? 0)) / 3
    ];
  }

  public function findPendingReviewsOlderThan(\DateTimeImmutable $deadline): array
  {
    return $this->createQueryBuilder('r')
      ->andWhere('r.status = :status')
      ->andWhere('r.createdAt < :deadline')
      ->setParameter('status', ReviewStatus::PENDING)
      ->setParameter('deadline', $deadline)
      ->orderBy('r.createdAt', 'ASC')
      ->getQuery()
      ->getResult();
  }

  public function findReviewsNeedingVerification(): array
  {
    return $this->createQueryBuilder('r')
      ->andWhere('r.status = :status')
      ->setParameter('status', ReviewStatus::NEED_VERIFICATION)
      ->orderBy('r.createdAt', 'ASC')
      ->getQuery()
      ->getResult();
  }
}
