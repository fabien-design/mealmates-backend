<?php

namespace App\Command;

use App\DataFixtures\AllergenFixtures;
use App\DataFixtures\FoodPreferenceFixtures; 
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-default-fixtures',
    description: 'Charge les données de base (préférences alimentaires et allergènes)',
)]
class LoadDefaultFixturesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private AllergenFixtures $allergenFixtures;
    private FoodPreferenceFixtures $foodPreferenceFixtures;

    public function __construct(
        EntityManagerInterface $entityManager, 
        AllergenFixtures $allergenFixtures,
        FoodPreferenceFixtures $foodPreferenceFixtures
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->allergenFixtures = $allergenFixtures;
        $this->foodPreferenceFixtures = $foodPreferenceFixtures;
    }

    protected function configure(): void
    {
        $this
            ->addOption('only-allergens', null, InputOption::VALUE_NONE, 'Charge uniquement les allergènes')
            ->addOption('only-preferences', null, InputOption::VALUE_NONE, 'Charge uniquement les préférences alimentaires');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $onlyAllergens = $input->getOption('only-allergens');
        $onlyPreferences = $input->getOption('only-preferences');

        if ($onlyAllergens && $onlyPreferences) {
            $onlyAllergens = false;
            $onlyPreferences = false;
        }

        $purger = new ORMPurger($this->entityManager);
    
        $loader = new Loader();
    
        if (!$onlyPreferences) {
            $io->info('Chargement des allergènes...');
            $loader->addFixture($this->allergenFixtures);
            $io->success(sprintf('%s allergènes prêts à être chargés', count(AllergenFixtures::ALLERGENS)));
        }

        if (!$onlyAllergens) {
            $io->info('Chargement des préférences alimentaires...');
            $loader->addFixture($this->foodPreferenceFixtures);
            $io->success(sprintf('%s préférences alimentaires prêtes à être chargées', count(FoodPreferenceFixtures::PREFERENCES)));
        }

        // Exécution des fixtures
        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $io->success('Les fixtures de base ont été chargées avec succès.');

        return Command::SUCCESS;
    }
}
