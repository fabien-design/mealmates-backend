<?php

namespace App\Security;

use App\Entity\User;
use App\Enums\UserStatus;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Vous  devez vérifier votre adresse e-mail avant de vous connecter.');
        }

        if ($user->getStatus() === UserStatus::REJECTED) {
            throw new CustomUserMessageAccountStatusException('Votre compte a été banni pour cause de ' . $user->getModerationComment() . '. Veuillez contacter le support pour plus d\'informations.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-auth checks needed
    }
}
