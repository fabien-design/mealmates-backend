<?php

namespace App\Command;

use App\Service\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-transaction-reservation-expiration-date',
    description: 'Verifies the expiration date of transaction reservations',
)]
class VerifyTransactionReservationExpirationDateCommand extends Command
{
    public function __construct(private readonly ReservationService $reservationService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Verifying Transaction Reservation Expiration Dates');

        $count = $this->reservationService->processExpiredReservations();
        $io->success(sprintf('Processed %d expired reservations.', $count));

        return Command::SUCCESS;
    }
}
