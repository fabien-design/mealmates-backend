<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserProgress;
use App\Enums\ProgressType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'app:update-consecutive-days',
  description: 'Update consecutive days login tracking for users',
)]
class UpdateConsecutiveDaysCommand extends Command
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
    $io->title('Updating consecutive days login tracking');

    // Get all users
    $users = $this->entityManager->getRepository(User::class)->findAll();
    $updatedCount = 0;

    foreach ($users as $user) {
      // Get the last login date
      $lastLogin = $user->getLastLogin();
      if (!$lastLogin) {
        continue;
      }

      // Get the consecutive days progress
      $progress = $user->getProgressByType(ProgressType::CONSECUTIVE_DAYS->value);
      if (!$progress) {
        $progress = new UserProgress();
        $progress->setUser($user);
        $progress->setProgressType(ProgressType::CONSECUTIVE_DAYS->value);
        $progress->setCurrentValue(1); // Start with 1 day
        $progress->setLastUpdated(new \DateTimeImmutable());
        $this->entityManager->persist($progress);
        $updatedCount++;
        continue;
      }

      // Check if the last login was yesterday
      $yesterday = new \DateTime('yesterday');
      $lastLoginDate = new \DateTime($lastLogin->format('Y-m-d'));
      $today = new \DateTime('today');

      if ($lastLoginDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        // They logged in yesterday, increment the counter
        $progress->incrementValue(1);
        $updatedCount++;
      } elseif ($lastLoginDate->format('Y-m-d') < $yesterday->format('Y-m-d')) {
        // They didn't log in yesterday, reset counter to 1
        $progress->setCurrentValue(1);
        $updatedCount++;
      }
      // If they last logged in today, do nothing

      $progress->setLastUpdated(new \DateTimeImmutable());
      $this->entityManager->persist($progress);
    }

    $this->entityManager->flush();

    $io->success(sprintf('Updated consecutive days tracking for %d users', $updatedCount));

    return Command::SUCCESS;
  }
}
