<?php

namespace App\Command;

use App\Service\QrCodeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-transaction-qrcode-expiration-date',
    description: 'Verifies the expiration date of transaction qr codes',
)]
class VerifyTransactionQrCodeExpirationDateCommand extends Command
{
    public function __construct(private readonly QrCodeService $qrCodeService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Verifying Transaction Qr code Expiration Dates');

        $count = $this->qrCodeService->cleanupExpiredQrCodes();
        $io->success(sprintf('Processed %d expired qr codes.', $count));

        return Command::SUCCESS;
    }
}
