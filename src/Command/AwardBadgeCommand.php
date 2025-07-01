<?php

namespace App\Command;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Enums\BadgeType;
use App\Enums\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:award-badge',
    description: 'Award a badge to a user',
)]
class AwardBadgeCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID')
            ->addArgument('badge-id', InputArgument::REQUIRED, 'Badge ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user-id');
        $badgeId = $input->getArgument('badge-id');

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error("User not found with ID: {$userId}");
            return Command::FAILURE;
        }

        $badge = $this->entityManager->getRepository(Badge::class)->find($badgeId);
        if (!$badge) {
            $io->error("Badge not found with ID: {$badgeId}");
            return Command::FAILURE;
        }

        // Vérifier si l'utilisateur a déjà ce badge
        $existingBadge = $this->entityManager->getRepository(UserBadge::class)->findOneBy([
            'user' => $user,
            'badge' => $badge
        ]);

        if ($existingBadge) {
            $io->warning("User already has this badge");
            return Command::SUCCESS;
        }

        // Créer une nouvelle attribution de badge
        $userBadge = new UserBadge();
        $userBadge->setUser($user);
        $userBadge->setBadge($badge);
        
        // La date est automatiquement définie dans le constructeur de UserBadge

        // Persister l'attribution du badge
        $this->entityManager->persist($userBadge);
        
        // Ajouter des crédits à l'utilisateur
        $bonusCredits = 25;
        $user->addCredits($bonusCredits);
        
        // Créer une notification pour informer l'utilisateur
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(NotificationType::BADGE->value);
        $notification->setContent([
            'title' => 'Nouveau badge débloqué !',
            'message' => 'Félicitations ! Vous avez débloqué le badge : ' . $badge->getName(),
            'badgeId' => $badge->getId()
        ]);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $io->success("Badge '{$badge->getName()}' successfully awarded to {$user->getEmail()}");
        return Command::SUCCESS;
    }
}
