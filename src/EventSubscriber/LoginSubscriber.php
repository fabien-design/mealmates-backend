<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserProgress;
use App\Enums\BadgeType;
use App\Enums\ProgressType;
use App\Repository\BadgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LoginSubscriber implements EventSubscriberInterface
{
  private TokenStorageInterface $tokenStorage;
  private EntityManagerInterface $entityManager;
  private BadgeRepository $badgeRepository;

  public function __construct(
    TokenStorageInterface $tokenStorage,
    EntityManagerInterface $entityManager,
    BadgeRepository $badgeRepository
  ) {
    $this->tokenStorage = $tokenStorage;
    $this->entityManager = $entityManager;
    $this->badgeRepository = $badgeRepository;
  }

  public static function getSubscribedEvents(): array
  {
    return [
      SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin',
    ];
  }

  public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
  {
    $user = $event->getAuthenticationToken()->getUser();

    if (!$user instanceof User) {
      return;
    }

    // Update last login
    $user->setLastLogin(new \DateTimeImmutable());

    // Check for consecutive days
    $this->updateConsecutiveDays($user);

    // Save changes
    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  private function updateConsecutiveDays(User $user): void
  {
    $progress = $user->getProgressByType(ProgressType::CONSECUTIVE_DAYS->value);
    $lastLogin = $user->getLastLogin();

    if (!$progress) {
      $progress = new UserProgress();
      $progress->setUser($user);
      $progress->setProgressType(ProgressType::CONSECUTIVE_DAYS->value);
      $progress->setCurrentValue(1); // First day
      $progress->setLastUpdated(new \DateTimeImmutable());
      $this->entityManager->persist($progress);
      return;
    }

    // Check if the last login was yesterday
    if ($lastLogin) {
      $yesterday = new \DateTime('yesterday');
      $lastLoginDate = new \DateTime($lastLogin->format('Y-m-d'));

      if ($lastLoginDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        // They logged in yesterday, increment the counter
        $progress->incrementValue(1);
      } elseif ($lastLoginDate->format('Y-m-d') < $yesterday->format('Y-m-d')) {
        // They didn't log in yesterday, reset counter to 1
        $progress->setCurrentValue(1);
      }
      // If they last logged in today, do nothing

      $progress->setLastUpdated(new \DateTimeImmutable());
      $this->entityManager->persist($progress);
    }
  }
}
