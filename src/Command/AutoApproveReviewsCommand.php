<?php

namespace App\Command;

use App\Entity\Review;
use App\Entity\User;
use App\Enums\ReviewStatus;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'app:auto-approve-reviews',
  description: 'Approuve automatiquement les évaluations en attente depuis un certain temps'
)]
class AutoApproveReviewsCommand extends Command
{

  private const DEFAULT_HOURS = 72;
  public function __construct(
    private EntityManagerInterface $entityManager,
    private ReviewRepository $reviewRepository
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this
      ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Nombre d\'heures après lesquelles une évaluation est automatiquement approuvée', self::DEFAULT_HOURS);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $hours = (int) $input->getOption('hours');

    $io->title(sprintf(
      'Approbation automatique des évaluations en attente depuis plus de %d heures',
      $hours
    ));

    $deadline = new \DateTimeImmutable('-' . $hours . ' hours');

    $pendingReviews = $this->reviewRepository->findPendingReviewsOlderThan($deadline);

    $totalCount = count($pendingReviews);

    if ($totalCount === 0) {
      $io->success('Aucune évaluation à approuver automatiquement.');
      return Command::SUCCESS;
    }

    $io->progressStart($totalCount);
    $approvedCount = 0;

    /** @var Review $review */
    foreach ($pendingReviews as $review) {
      $io->progressAdvance();

      $review->setStatus(ReviewStatus::APPROVED);
      $review->setModeratedAt(new \DateTimeImmutable());
      $this->entityManager->persist($review);

      $this->updateUserRating($review->getReviewed());

      $approvedCount++;

      // Flush par lots pour optimiser les performances -- claude.ai
      if ($approvedCount % 20 === 0) {
        $this->entityManager->flush();
      }
    }

    // Flush final pour les entités restantes
    if ($approvedCount % 20 !== 0) {
      $this->entityManager->flush();
    }

    $io->progressFinish();

    $io->success(sprintf(
      '%d évaluations sur %d ont été automatiquement approuvées',
      $approvedCount,
      $totalCount
    ));

    return Command::SUCCESS;
  }

  private function updateUserRating(User $user): void
  {
    $ratings = $this->reviewRepository->findAverageRatingsForUser($user);

    $user->setAverageRating($ratings['avgOverall']);
    $this->entityManager->persist($user);
  }
}
