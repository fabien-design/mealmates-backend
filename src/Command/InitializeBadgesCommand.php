<?php

namespace App\Command;

use App\Entity\Badge;
use App\Enums\BadgeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:initialize-badges',
    description: 'Initialize default badges for the gamification system',
)]
class InitializeBadgesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Initializing badges for the gamification system');

        $badges = [
            [
                'name' => 'Première offre',
                'description' => 'Vous avez publié votre première offre',
                'type' => BadgeType::OFFER_CREATED->value,
                'icon' => 'tag',
                'threshold' => 1,
            ],
            [
                'name' => 'Vendeur débutant',
                'description' => 'Vous avez publié 5 offres',
                'type' => BadgeType::OFFER_CREATED->value,
                'icon' => 'tag-plus',
                'threshold' => 5,
            ],
            [
                'name' => 'Vendeur expérimenté',
                'description' => 'Vous avez publié 20 offres',
                'type' => BadgeType::OFFER_CREATED->value,
                'icon' => 'store',
                'threshold' => 20,
            ],
            [
                'name' => 'Vendeur professionnel',
                'description' => 'Vous avez publié 50 offres',
                'type' => BadgeType::OFFER_CREATED->value,
                'icon' => 'shop',
                'threshold' => 50,
            ],
            [
                'name' => 'Vendeur d\'élite',
                'description' => 'Vous avez publié 100 offres',
                'type' => BadgeType::OFFER_CREATED->value,
                'icon' => 'award',
                'threshold' => 100,
            ],

            // Food saved badges
            [
                'name' => 'Premier sauvetage',
                'description' => 'Vous avez sauvé votre première portion de nourriture',
                'type' => BadgeType::FOOD_SAVED->value,
                'icon' => 'salad',
                'threshold' => 1,
            ],
            [
                'name' => 'Sauveur débutant',
                'description' => 'Vous avez sauvé 10 portions de nourriture',
                'type' => BadgeType::FOOD_SAVED->value,
                'icon' => 'cooking-pot',
                'threshold' => 10,
            ],
            [
                'name' => 'Sauveur intermédiaire',
                'description' => 'Vous avez sauvé 50 portions de nourriture',
                'type' => BadgeType::FOOD_SAVED->value,
                'icon' => 'utensils',
                'threshold' => 50,
            ],
            [
                'name' => 'Super sauveur',
                'description' => 'Vous avez sauvé 100 portions de nourriture',
                'type' => BadgeType::FOOD_SAVED->value,
                'icon' => 'apple',
                'threshold' => 100,
            ],
            [
                'name' => 'Héros anti-gaspillage',
                'description' => 'Vous avez sauvé 500 portions de nourriture',
                'type' => BadgeType::FOOD_SAVED->value,
                'icon' => 'chef-hat',
                'threshold' => 500,
            ],

            // Transaction badges
            [
                'name' => 'Première transaction',
                'description' => 'Vous avez complété votre première transaction',
                'type' => BadgeType::TRANSACTIONS_COMPLETED->value,
                'icon' => 'receipt',
                'threshold' => 1,
            ],
            [
                'name' => 'Commerçant débutant',
                'description' => 'Vous avez complété 5 transactions',
                'type' => BadgeType::TRANSACTIONS_COMPLETED->value,
                'icon' => 'wallet',
                'threshold' => 5,
            ],
            [
                'name' => 'Commerçant régulier',
                'description' => 'Vous avez complété 20 transactions',
                'type' => BadgeType::TRANSACTIONS_COMPLETED->value,
                'icon' => 'banknote',
                'threshold' => 20,
            ],
            [
                'name' => 'Commerçant expérimenté',
                'description' => 'Vous avez complété 50 transactions',
                'type' => BadgeType::TRANSACTIONS_COMPLETED->value,
                'icon' => 'landmark',
                'threshold' => 50,
            ],
            [
                'name' => 'Roi du commerce',
                'description' => 'Vous avez complété 100 transactions',
                'type' => BadgeType::TRANSACTIONS_COMPLETED->value,
                'icon' => 'gem',
                'threshold' => 100,
            ],

            // Review badges
            [
                'name' => 'Première évaluation',
                'description' => 'Vous avez reçu votre première évaluation positive',
                'type' => BadgeType::REVIEWS_RECEIVED->value,
                'icon' => 'thumbs-up',
                'threshold' => 1,
            ],
            [
                'name' => 'Bien évalué',
                'description' => 'Vous avez reçu 5 évaluations positives',
                'type' => BadgeType::REVIEWS_RECEIVED->value,
                'icon' => 'medal',
                'threshold' => 5,
            ],
            [
                'name' => 'Très apprécié',
                'description' => 'Vous avez reçu 20 évaluations positives',
                'type' => BadgeType::REVIEWS_RECEIVED->value,
                'icon' => 'star',
                'threshold' => 20,
            ],
            [
                'name' => 'Hautement recommandé',
                'description' => 'Vous avez reçu 50 évaluations positives',
                'type' => BadgeType::REVIEWS_RECEIVED->value,
                'icon' => 'trophy',
                'threshold' => 50,
            ],

            // Review given badges
            [
                'name' => 'Premier critique',
                'description' => 'Vous avez laissé votre premier avis',
                'type' => BadgeType::REVIEWS_GIVEN->value,
                'icon' => 'message-square',
                'threshold' => 1,
            ],
            [
                'name' => 'Critique actif',
                'description' => 'Vous avez laissé 5 avis',
                'type' => BadgeType::REVIEWS_GIVEN->value,
                'icon' => 'message-circle',
                'threshold' => 5,
            ],
            [
                'name' => 'Critique expérimenté',
                'description' => 'Vous avez laissé 20 avis',
                'type' => BadgeType::REVIEWS_GIVEN->value,
                'icon' => 'message-square-plus',
                'threshold' => 20,
            ],
            [
                'name' => 'Critique d\'élite',
                'description' => 'Vous avez laissé 50 avis',
                'type' => BadgeType::REVIEWS_GIVEN->value,
                'icon' => 'megaphone',
                'threshold' => 50,
            ],

            // Account age badges
            [
                'name' => 'Nouveau membre',
                'description' => 'Vous êtes membre depuis 1 jour',
                'type' => BadgeType::ACCOUNT_AGE->value,
                'icon' => 'user',
                'threshold' => 1,
            ],
            [
                'name' => 'Membre depuis une semaine',
                'description' => 'Vous êtes membre depuis 7 jours',
                'type' => BadgeType::ACCOUNT_AGE->value,
                'icon' => 'user-circle',
                'threshold' => 7,
            ],
            [
                'name' => 'Membre depuis un mois',
                'description' => 'Vous êtes membre depuis 30 jours',
                'type' => BadgeType::ACCOUNT_AGE->value,
                'icon' => 'user-check',
                'threshold' => 30,
            ],
            [
                'name' => 'Membre fidèle',
                'description' => 'Vous êtes membre depuis 3 mois',
                'type' => BadgeType::ACCOUNT_AGE->value,
                'icon' => 'user-plus',
                'threshold' => 90,
            ],
            [
                'name' => 'Membre depuis un an',
                'description' => 'Vous êtes membre depuis 365 jours',
                'type' => BadgeType::ACCOUNT_AGE->value,
                'icon' => 'crown',
                'threshold' => 365,
            ],

            // Consecutive days badges
            [
                'name' => 'Première connexion',
                'description' => 'Vous vous êtes connecté 1 jour de suite',
                'type' => BadgeType::CONSECUTIVE_DAYS->value,
                'icon' => 'log-in',
                'threshold' => 1,
            ],
            [
                'name' => 'Fidèle à la semaine',
                'description' => 'Vous vous êtes connecté 7 jours consécutifs',
                'type' => BadgeType::CONSECUTIVE_DAYS->value,
                'icon' => 'calendar',
                'threshold' => 7,
            ],
            [
                'name' => 'Accro du mois',
                'description' => 'Vous vous êtes connecté 30 jours consécutifs',
                'type' => BadgeType::CONSECUTIVE_DAYS->value,
                'icon' => 'calendar-check',
                'threshold' => 30,
            ],
            [
                'name' => 'Fidèle inconditionnel',
                'description' => 'Vous vous êtes connecté 100 jours consécutifs',
                'type' => BadgeType::CONSECUTIVE_DAYS->value,
                'icon' => 'calendar-heart',
                'threshold' => 100,
            ],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($badges as $badgeData) {
            $existing = $this->entityManager->getRepository(Badge::class)->findOneBy([
                'name' => $badgeData['name'],
                'type' => $badgeData['type'],
            ]);

            if ($existing) {
                $skippedCount++;
                continue;
            }

            $badge = new Badge();
            $badge->setName($badgeData['name']);
            $badge->setDescription($badgeData['description']);
            $badge->setType($badgeData['type']);
            $badge->setIcon($badgeData['icon']);
            $badge->setThreshold($badgeData['threshold']);

            $this->entityManager->persist($badge);
            $createdCount++;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Badges initialized successfully. Created: %d, Skipped (already exist): %d',
            $createdCount,
            $skippedCount
        ));

        return Command::SUCCESS;
    }
}