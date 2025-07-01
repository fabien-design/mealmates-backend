<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enums\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
  private EntityManagerInterface $entityManager;

  public function __construct(EntityManagerInterface $entityManager)
  {
    $this->entityManager = $entityManager;
  }

  public function createNotification(
    User $user,
    string $title,
    string $message,
    NotificationType $type,
    array $metadata = []
  ): Notification {
    $content = [
      'title' => $title,
      'message' => $message,
    ];

    if (!empty($metadata)) {
      $content = array_merge($content, $metadata);
    }

    $notification = new Notification();
    $notification->setUser($user);
    $notification->setContent($content);
    $notification->setType($type->value);
    $notification->setIsRead(false);
    $notification->setCreatedAt(new \DateTimeImmutable());

    $this->entityManager->persist($notification);
    $this->entityManager->flush();

    return $notification;
  }

  public function markAsRead(Notification $notification): void
  {
    $notification->setIsRead(true);
    $this->entityManager->persist($notification);
    $this->entityManager->flush();
  }

  public function markAllAsRead(User $user): void
  {
    $notifications = $user->getNotifications();

    foreach ($notifications as $notification) {
      if (!$notification->isRead()) {
        $notification->setIsRead(true);
        $this->entityManager->persist($notification);
      }
    }

    $this->entityManager->flush();
  }
}
