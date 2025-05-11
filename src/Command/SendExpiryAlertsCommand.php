<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\Offer;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsCommand(
  name: 'app:send-expiry-alerts',
  description: 'Envoie des alertes aux vendeurs pour les offres approchant de leur date de péremption',
)]
class SendExpiryAlertsCommand extends Command
{
  private const DAYS_BEFORE_ALERT = 2;

  public function __construct(
    private OfferRepository $offerRepository,
    private EntityManagerInterface $entityManager,
    private MailerInterface $mailer,
    private ParameterBagInterface $params
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force l\'exécution même si les alertes ont déjà été envoyées aujourd\'hui')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule l\'envoi sans réellement envoyer d\'emails ou créer de notifications');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $isDryRun = $input->getOption('dry-run');

    $io->title('Envoi des alertes de péremption');

    if ($isDryRun) {
      $io->note('Mode simulation activé - aucun email ne sera envoyé et aucune notification ne sera créée');
    }

    $expiryDate = new \DateTime();
    $expiryDate->add(new \DateInterval('P' . self::DAYS_BEFORE_ALERT . 'D'));
    $expiryDate->setTime(23, 59, 59);

    $io->info(sprintf('Recherche des offres expirant autour du %s', $expiryDate->format('Y-m-d')));

    $expiringOffers = $this->offerRepository->findExpiringOffers($expiryDate);

    if (empty($expiringOffers)) {
      $io->success('Aucune offre n\'expire bientôt.');
      return Command::SUCCESS;
    }

    $io->info(sprintf('%d offre(s) trouvée(s) qui expirent bientôt', count($expiringOffers)));

    $count = 0;
    $senderEmail = $this->params->get('app.email_sender');
    $senderName = $this->params->get('app.email_sender_name');
    $frontendUrl = $this->params->get('app.frontend_url');

    foreach ($expiringOffers as $offer) {
      $seller = $offer->getSeller();
      if (!$seller) {
        $io->warning(sprintf('Offre #%d n\'a pas de vendeur associé', $offer->getId()));
        continue;
      }

      if (!$seller->isVerified()) {
        $io->warning(sprintf('Le vendeur de l\'offre #%d n\'a pas vérifié son email', $offer->getId()));
        continue;
      }

      $io->text(sprintf(
        'Traitement de l\'offre #%d "%s" du vendeur %s',
        $offer->getId(),
        $offer->getName(),
        $seller->getEmail()
      ));

      if (!$isDryRun) {
        $content = [
          "message" => sprintf(
            'Votre offre "%s" expire dans %d jours. Pensez à ajuster son prix ou à la convertir en don.',
            $offer->getName(),
            self::DAYS_BEFORE_ALERT
          ),
          "offerId" => $offer->getId(),
        ];


        $notification = new Notification();
        $notification->setUser($seller);
        $notification->setTitle('Votre offre expire bientôt !');
        $notification->setContent($content);
        $notification->setType('expiry_alert');
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($notification);
      }

      // Envoyer un email
      $email = (new TemplatedEmail())
        ->from(new Address($senderEmail, $senderName))
        ->to($seller->getEmail())
        ->subject('Votre offre MealMates expire bientôt !')
        ->htmlTemplate('emails/expiry_alert.html.twig')
        ->context([
          'user' => $seller,
          'offer' => $offer,
          'days_remaining' => self::DAYS_BEFORE_ALERT,
          'edit_url' => sprintf('%s/app/edit-offer/%d', $frontendUrl, $offer->getId())
        ]);

      if (!$isDryRun) {
        $this->mailer->send($email);
        $offer->setExpiryAlertSent(true);
        $this->entityManager->persist($offer);
      }

      $count++;
      $io->text('Alerte envoyée avec succès.');
    }

    if (!$isDryRun) {
      $this->entityManager->flush();
    }

    $io->success(sprintf(
      '%d alerte(s) de péremption ont été %s',
      $count,
      $isDryRun ? 'simulée(s)' : 'envoyée(s)'
    ));

    return Command::SUCCESS;
  }
}
