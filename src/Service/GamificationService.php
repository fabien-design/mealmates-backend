<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Entity\Offer;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserProgress;
use App\Enums\BadgeType;
use App\Enums\NotificationType;
use App\Enums\ProgressType;
use App\Repository\BadgeRepository;
use App\Repository\OfferRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserProgressRepository;
use Doctrine\ORM\EntityManagerInterface;

class GamificationService
{
  private EntityManagerInterface $entityManager;
  private BadgeRepository $badgeRepository;
  private UserBadgeRepository $userBadgeRepository;
  private UserProgressRepository $userProgressRepository;
  private OfferRepository $offerRepository;
  private NotificationService $notificationService;

  public function __construct(
    EntityManagerInterface $entityManager,
    BadgeRepository $badgeRepository,
    UserBadgeRepository $userBadgeRepository,
    UserProgressRepository $userProgressRepository,
    OfferRepository $offerRepository,
    NotificationService $notificationService
  ) {
    $this->entityManager = $entityManager;
    $this->badgeRepository = $badgeRepository;
    $this->userBadgeRepository = $userBadgeRepository;
    $this->userProgressRepository = $userProgressRepository;
    $this->offerRepository = $offerRepository;
    $this->notificationService = $notificationService;
  }

  public function processOfferCreated(User $user): void
  {
    // Award credits for creating an offer
    $user->addCredits(10);

    // Update progress
    $this->updateProgress($user, ProgressType::OFFERS_CREATED, 1);

    // Get current count for badges
    $progress = $user->getProgressByType(ProgressType::OFFERS_CREATED->value);
    $offerCount = $progress ? $progress->getCurrentValue() : 1;

    // Check for badges
    $this->checkAndAwardBadges($user, BadgeType::OFFER_CREATED, $offerCount);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  public function processFoodSaved(User $user, float $foodAmount): void
  {
    // Award credits based on food amount saved
    $credits = (int) ($foodAmount * 5); // 5 credits per unit of food
    $user->addCredits($credits);

    // Update progress
    $this->updateProgress($user, ProgressType::FOOD_SAVED, (int) $foodAmount);

    // Get current saved food for badges
    $progress = $user->getProgressByType(ProgressType::FOOD_SAVED->value);
    $totalSaved = $progress ? $progress->getCurrentValue() : (int) $foodAmount;

    // Check for badges
    $this->checkAndAwardBadges($user, BadgeType::FOOD_SAVED, $totalSaved);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  public function processTransactionCompleted(User $user): void
  {
    // Award credits for completing a transaction
    $user->addCredits(15);

    // Update progress
    $this->updateProgress($user, ProgressType::TRANSACTIONS_COMPLETED, 1);

    // Get current count for badges
    $progress = $user->getProgressByType(ProgressType::TRANSACTIONS_COMPLETED->value);
    $transactionCount = $progress ? $progress->getCurrentValue() : 1;

    // Check for badges
    $this->checkAndAwardBadges($user, BadgeType::TRANSACTIONS_COMPLETED, $transactionCount);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  public function processReviewReceived(User $user): void
  {
    // Award credits for receiving a review
    $user->addCredits(5);

    // Update progress
    $this->updateProgress($user, ProgressType::REVIEWS_RECEIVED, 1);

    // Get current count for badges
    $progress = $user->getProgressByType(ProgressType::REVIEWS_RECEIVED->value);
    $reviewCount = $progress ? $progress->getCurrentValue() : 1;

    // Check for badges
    $this->checkAndAwardBadges($user, BadgeType::REVIEWS_RECEIVED, $reviewCount);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  public function processReviewGiven(User $user): void
  {
    // Award credits for giving a review
    $user->addCredits(3);

    // Update progress
    $this->updateProgress($user, ProgressType::REVIEWS_GIVEN, 1);

    // Get current count for badges
    $progress = $user->getProgressByType(ProgressType::REVIEWS_GIVEN->value);
    $reviewCount = $progress ? $progress->getCurrentValue() : 1;

    // Check for badges
    $this->checkAndAwardBadges($user, BadgeType::REVIEWS_GIVEN, $reviewCount);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  public function checkAccountAgeBadges(User $user, \DateTimeInterface $registrationDate): void
  {
    // Calculate account age in days
    $now = new \DateTime();
    $interval = $now->diff($registrationDate);
    $days = $interval->days;

    $this->checkAndAwardBadges($user, BadgeType::ACCOUNT_AGE, $days);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  private function updateProgress(User $user, ProgressType $progressType, int $increment): void
  {
    $progress = $this->userProgressRepository->findByUserAndType($user->getId(), $progressType->value);

    if (!$progress) {
      $progress = new UserProgress();
      $progress->setUser($user);
      $progress->setProgressType($progressType->value);
      $progress->setCurrentValue($increment);
    } else {
      $progress->incrementValue($increment);
    }

    $this->entityManager->persist($progress);
  }

  private function checkAndAwardBadges(User $user, BadgeType $badgeType, int $currentValue): void
  {
    // Get all badges of this type
    $badges = $this->badgeRepository->findByType($badgeType->value);

    // Get user badges of this type
    $userBadges = $this->userBadgeRepository->findByUserAndType($user->getId(), $badgeType->value);
    $userBadgeIds = array_map(function ($userBadge) {
      return $userBadge->getBadge()->getId();
    }, $userBadges);

    foreach ($badges as $badge) {
      // Skip if user already has this badge
      if (in_array($badge->getId(), $userBadgeIds)) {
        continue;
      }

      // Check if user has reached the threshold
      if ($currentValue >= $badge->getThreshold()) {
        $this->awardBadge($user, $badge);
      } else {
        // Update progress on badge
        foreach ($userBadges as $userBadge) {
          if ($userBadge->getBadge()->getId() === $badge->getId()) {
            $userBadge->setCurrentProgress($currentValue);
            $this->entityManager->persist($userBadge);
            break;
          }
        }
      }
    }
  }

  private function awardBadge(User $user, Badge $badge): void
  {
    $userBadge = new UserBadge();
    $userBadge->setUser($user);
    $userBadge->setBadge($badge);

    // Award bonus credits for getting a badge
    $bonusCredits = 25; // Default bonus
    $user->addCredits($bonusCredits);

    $this->entityManager->persist($userBadge);

    // Send notification
    $this->notificationService->createNotification(
      $user,
      "Félicitations !",
      "Vous avez obtenu le badge " . $badge->getName() . " et gagné " . $bonusCredits . " crédits !",
      NotificationType::BADGE,
      ['badgeId' => $badge->getId(), 'credits' => $bonusCredits]
    );
  }
}
