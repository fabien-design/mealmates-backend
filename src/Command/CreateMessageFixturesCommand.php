<?php

namespace App\Command;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Offer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-message-fixtures',
    description: 'Creates message fixtures for testing',
)]
class CreateMessageFixturesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $offers = $this->entityManager->getRepository(Offer::class)->findAll();
        $users = $this->entityManager->getRepository(User::class)->findAll();
        
        if (empty($offers) || count($users) < 2) {
            $io->error('No offers or insufficient users found. Please load other fixtures first.');
            return Command::FAILURE;
        }
        
        $conversations = [];
        $messageCount = 0;
        
        // Créer quelques conversations
        foreach (array_slice($offers, 0, 5) as $offer) {
            $seller = $offer->getSeller();
            
            // Trouver un acheteur différent du vendeur
            $potentialBuyers = array_filter($users, function ($user) use ($seller) {
                return $user->getId() !== $seller->getId();
            });
            
            if (empty($potentialBuyers)) {
                continue;
            }
            
            $buyer = $potentialBuyers[array_rand($potentialBuyers)];
            
            $conversation = new Conversation();
            $conversation->setOffer($offer);
            $conversation->setSeller($seller);
            $conversation->setBuyer($buyer);
            
            $this->entityManager->persist($conversation);
            $conversations[] = [
                'conversation' => $conversation,
                'seller' => $seller,
                'buyer' => $buyer
            ];
        }
        
        $this->entityManager->flush();
        
        $io->info(sprintf('Created %d test conversations', count($conversations)));
        
        // Ajouter des messages à chaque conversation
        foreach ($conversations as $convData) {
            $conversation = $convData['conversation'];
            $seller = $convData['seller'];
            $buyer = $convData['buyer'];
            
            $messageTexts = [
                'buyer' => [
                    'Bonjour, votre offre est-elle toujours disponible ?',
                    'Super ! Est-ce que je peux venir la chercher ce soir ?',
                    'Parfait, je viendrai vers 18h alors. Où dois-je vous retrouver exactement ?',
                    'Merci beaucoup ! À ce soir.',
                ],
                'seller' => [
                    'Bonjour, oui elle est toujours disponible !',
                    'Oui, ce soir c\'est possible. Je suis disponible à partir de 17h.',
                    'On peut se retrouver devant la boulangerie au 12 rue des Lilas.',
                    'Très bien, à ce soir !',
                ]
            ];
            
            $messageCount = 0;
            $sentTime = new \DateTimeImmutable('-' . rand(1, 10) . ' days');
            
            // Créer l'échange de messages
            for ($i = 0; $i < min(count($messageTexts['buyer']), count($messageTexts['seller'])); $i++) {
                // Message de l'acheteur
                $buyerMessage = new Message();
                $buyerMessage->setSender($buyer);
                $buyerMessage->setConversation($conversation);
                $buyerMessage->setContent($messageTexts['buyer'][$i]);
                $buyerMessage->setCreatedAt(new \DateTimeImmutable('@' . $sentTime->modify('+10 minutes')->getTimestamp()));
                $buyerMessage->setIsRead(true);
                
                $this->entityManager->persist($buyerMessage);
                $messageCount++;
                
                // Message du vendeur
                $sellerMessage = new Message();
                $sellerMessage->setSender($seller);
                $sellerMessage->setConversation($conversation);
                $sellerMessage->setContent($messageTexts['seller'][$i]);
                $sellerMessage->setCreatedAt(new \DateTimeImmutable('@' . $sentTime->modify('+20 minutes')->getTimestamp()));
                $sellerMessage->setIsRead($i < count($messageTexts['seller']) - 1); // Le dernier message n'est pas lu
                
                $this->entityManager->persist($sellerMessage);
                $messageCount++;
            }

            $this->entityManager->persist($conversation);
        }
        
        $this->entityManager->flush();
        
        $io->success(sprintf('Created %d test messages in %d conversations', $messageCount, count($conversations)));
        
        return Command::SUCCESS;
    }
}
