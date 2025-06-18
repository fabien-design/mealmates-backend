<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Notifier
{
  public function __construct(
    private readonly LoggerInterface $logger,
    private readonly EntityManagerInterface $entityManager,
  ) {}

  public function emit(User $user, string $notificationClass, array $content = []): bool
  {
    try {
      $notification = new Notification();
      $notification->setUser($user)
        ->setType($notificationClass)
        ->setContent($content)
        ->setIsRead(false)
        ->setCreatedAt(new \DateTimeImmutable());

      $this->entityManager->persist($notification);

      $this->entityManager->flush();

      return true;
    } catch (\Exception $e) {
      $this->logger->error('Erreur lors de l\'émission de la notification', [
        'user_id' => $user->getId(),
        'type' => $notificationClass,
        'exception' => $e->getMessage()
      ]);

      return false;
    }
  }
}
