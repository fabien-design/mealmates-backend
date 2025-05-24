<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Image;
use App\Entity\Message;
use App\Entity\Offer;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\OfferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\SerializerInterface;

class MessageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private OfferRepository $offerRepository,
        private UserRepository $userRepository,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Crée ou récupère une conversation entre un acheteur et un vendeur concernant une offre
     */
    public function getOrCreateConversation(int $offerId, int $buyerId, int $sellerId): Conversation
    {
        $conversation = $this->conversationRepository->findByOfferAndUsers($offerId, $buyerId, $sellerId);

        if (!$conversation) {
            $offer = $this->offerRepository->find($offerId);
            $buyer = $this->userRepository->find($buyerId);
            $seller = $this->userRepository->find($sellerId);

            if (!$offer || !$buyer || !$seller) {
                throw new \InvalidArgumentException('Offre, acheteur ou vendeur non trouvé');
            }

            $conversation = new Conversation();
            $conversation->setOffer($offer);
            $conversation->setBuyer($buyer);
            $conversation->setSeller($seller);

            $this->entityManager->persist($conversation);
            $this->entityManager->flush();
        }

        return $conversation;
    }

    /**
     * Envoie un nouveau message dans une conversation
     */
    public function sendMessage(
        Conversation $conversation,
        User $sender,
        ?string $content,
        ?array $imageFiles = null
    ): Message {
        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($sender);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTimeImmutable());
        $message->setIsRead(false);
        if ($imageFiles) {
            foreach ($imageFiles as $imageFile) {
                if (!$imageFile instanceof UploadedFile) {
                    throw new \InvalidArgumentException('Invalid file type provided');
                }
                $newImage = new Image();
                $newImage->setFile($imageFile);
                $newImage->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($newImage);

                $message->addImage($newImage);
            }
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * Récupère les messages d'une conversation
     */
    public function getMessages(Conversation $conversation, int $limit = 50, int $offset = 0): array
    {
        return $this->messageRepository->findByConversation($conversation, $limit, $offset);
    }

    /**
     * Get new messages in a conversation since a specific timestamp
     */
    public function getNewMessages(Conversation $conversation, User $user, \DateTimeImmutable $since): array
    {
        if (!in_array($user, $conversation->getParticipants())) {
            throw new \InvalidArgumentException('User is not a participant in this conversation');
        }

        return $this->messageRepository->findNewMessages($user, $since);
    }

    /**
     * Marque les messages d'une conversation comme lus pour un utilisateur
     */
    public function markMessagesAsRead(Conversation $conversation, User $user): void
    {
        $this->messageRepository->markAsReadInConversation($conversation, $user);
        $this->entityManager->flush();
    }

    public function countUnreadMessages(User $user): int
    {
        return $this->messageRepository->countUnreadByUser($user);
    }

    /**
     * Get the timestamp of the latest message in a conversation
     */
    public function getLatestMessageTimestamp(Conversation $conversation): ?string
    {
        $latestMessage = $this->messageRepository->findLatestInConversation($conversation);
        return $latestMessage ? $latestMessage->getCreatedAt()->format('Y-m-d H:i:s') : null;
    }

    public function getPredefinedMessages(): array
    {
        return [
            'Bonjour, votre offre est-elle toujours disponible ?',
            'Je suis intéressé(e) par votre annonce. Pouvons-nous convenir d\'un rendez-vous ?',
            'À quelle heure seriez-vous disponible pour la remise ?',
            'Merci pour votre réponse !',
            'Parfait, c\'est noté !',
            'Pouvez-vous me donner plus de détails sur le produit ?'
        ];
    }
}
