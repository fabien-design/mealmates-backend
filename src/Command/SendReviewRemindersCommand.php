<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\Notification\TransactionNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'app:send-review-reminders',
  description: 'Envoie des notifications de rappel pour évaluer les transactions complétées'
)]
class SendReviewRemindersCommand extends Command
{
  private const DAYS = 1;

  public function __construct(
    private EntityManagerInterface $entityManager,
    private TransactionNotificationService $notificationService,
    private TransactionRepository $transactionRepository
  ) {
    parent::__construct();
  }

  protected function configure(): void {}

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $days = self::DAYS;

    $io->title(sprintf(
      'Envoi des notifs de rappel pour évaluer les transactions finis il y a %d jour(s)',
      $days
    ));

    $now = new \DateTimeImmutable();
    $startDate = $now->modify("-$days days")->modify('00:00:00');
    $endDate = $now->modify("-$days days")->modify('23:59:59');

    $transactions = $this->transactionRepository->findTransactionsByTransferDate($startDate, $endDate);

    $sentCount = 0;
    $totalCount = count($transactions);

    $io->progressStart($totalCount);

    /** @var Transaction $transaction */
    foreach ($transactions as $transaction) {
      $io->progressAdvance();

      $buyerReview = $transaction->getBuyerReview();
      $sellerReview = $transaction->getSellerReview();

      if (!$buyerReview || !$sellerReview) {
        $result = $this->notificationService->notifyReviewReminder($transaction);
        if ($result) {
          $sentCount++;
        }
      }
    }

    $io->progressFinish();

    $io->success(sprintf(
      '%d rappels envoyés sur %d transactions',
      $sentCount,
      $totalCount
    ));

    return Command::SUCCESS;
  }
}
