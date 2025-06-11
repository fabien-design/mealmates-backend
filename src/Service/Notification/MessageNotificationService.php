<?php

use App\Entity\User;
use App\Service\Notification\Notifier;

class MessageNotificationService
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {
    }

    public function sendMessageNotification(User $user, string $notificationClass, array $content): bool
    {
        return $this->notifier->emit($user, $notificationClass, $content);
    }
}
