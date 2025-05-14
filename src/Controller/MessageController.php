<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/v1')]
#[OA\Tag(name: 'Messagerie')]
class MessageController extends AbstractController
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private ConversationRepository $conversationRepository,
    private MessageService $messageService,
    private SerializerInterface $serializer
  ) {
  }

  #[Route('/conversations', name: 'api_conversations_list', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Response(
    response: 200,
    description: 'Liste des conversations de l\'utilisateur',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: new Model(type: Conversation::class, groups: ['conversation:read']))
    )
  )]
  public function getConversations(): JsonResponse
  {
    /** @var User $user */
    $user = $this->getUser();

    $conversations = $this->conversationRepository->findByUser($user);

    return $this->json($conversations, Response::HTTP_OK, [], [
      'groups' => ['conversation:read', 'offer:read']
    ]);
  }

  #[Route('/conversations/{id}/messages', name: 'api_conversation_messages', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'ID de la conversation',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Parameter(
    name: 'limit',
    description: 'Nombre de messages à récupérer',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 50)
  )]
  #[OA\Parameter(
    name: 'offset',
    description: 'Offset pour la pagination',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 0)
  )]
  #[OA\Response(
    response: 200,
    description: 'Liste des messages d\'une conversation',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: new Model(type: Message::class, groups: ['message:read']))
    )
  )]
  #[OA\Response(
    response: 403,
    description: 'Accès refusé, l\'utilisateur n\'est pas un participant de la conversation'
  )]
  #[OA\Response(
    response: 404,
    description: 'Conversation non trouvée'
  )]
  public function getMessages(int $id, Request $request): JsonResponse
  {
    /** @var User $user */
    $user = $this->getUser();

    $conversation = $this->conversationRepository->find($id);

    if (!$conversation) {
      return $this->json(['message' => 'Conversation not found'], Response::HTTP_NOT_FOUND);
    }

    if (!in_array($user, $conversation->getParticipants())) {
      return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $limit = $request->query->getInt('limit', 50);
    $offset = $request->query->getInt('offset', 0);

    $messages = $this->messageService->getMessages($conversation, $limit, $offset);

    // Marquer les messages comme lus
    $this->messageService->markMessagesAsRead($conversation, $user);

    return $this->json($messages, Response::HTTP_OK, [], [
      'groups' => ['message:read', 'user:read']
    ]);
  }

  #[Route('/conversations/offer/{offerId}/with/{userId}', name: 'api_conversation_with_user', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'offerId',
    description: 'ID de l\'offre',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Parameter(
    name: 'userId',
    description: 'ID de l\'autre utilisateur',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Conversation trouvée ou créée',
    content: new Model(type: Conversation::class, groups: ['conversation:read'])
  )]
  public function getOrCreateConversation(int $offerId, int $userId): JsonResponse
  {
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    $conversation = $this->messageService->getOrCreateConversation(
      $offerId,
      $currentUser->getId(),
      $userId
    );

    return $this->json($conversation, Response::HTTP_OK, [], [
      'groups' => ['conversation:read', 'offer:read']
    ]);
  }

  #[Route('/conversations/{id}/messages', name: 'api_send_message', methods: ['POST'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'ID de la conversation',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\RequestBody(
    description: 'Contenu du message',
    required: true,
    content: new OA\MediaType(
      mediaType: 'multipart/form-data',
      schema: new OA\Schema(
        required: ['content'],
        properties: [
          new OA\Property(property: 'content', type: 'string', example: 'Bonjour, je suis intéressé par votre offre'),
          new OA\Property(property: 'image', type: 'string', format: 'binary')
        ]
      )
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'Message envoyé',
    content: new Model(type: Message::class, groups: ['message:read'])
  )]
  #[OA\Response(
    response: 403,
    description: 'Accès refusé, l\'utilisateur n\'est pas un participant de la conversation'
  )]
  #[OA\Response(
    response: 404,
    description: 'Conversation non trouvée'
  )]
  public function sendMessage(int $id, Request $request): JsonResponse
  {
    /** @var User $user */
    $user = $this->getUser();

    $conversation = $this->conversationRepository->find($id);

    if (!$conversation) {
      return $this->json(['message' => 'Conversation not found'], Response::HTTP_NOT_FOUND);
    }

    if (!in_array($user, $conversation->getParticipants())) {
      return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $content = $request->request->get('content');
    $imageFile = $request->files->get('image');

    // Vérification qu'au moins un contenu est fourni
    if (empty($content) && !$imageFile) {
      return $this->json(['message' => 'Message content or image is required'], Response::HTTP_BAD_REQUEST);
    }

    $message = $this->messageService->sendMessage($conversation, $user, $content, $imageFile);

    return $this->json($message, Response::HTTP_CREATED, [], [
      'groups' => ['message:read', 'user:read']
    ]);
  }

  #[Route('/conversations/{id}/read', name: 'api_mark_messages_read', methods: ['PUT'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'ID de la conversation',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Messages marqués comme lus'
  )]
  #[OA\Response(
    response: 403,
    description: 'Accès refusé, l\'utilisateur n\'est pas un participant de la conversation'
  )]
  #[OA\Response(
    response: 404,
    description: 'Conversation non trouvée'
  )]
  public function markMessagesAsRead(int $id): JsonResponse
  {
    /** @var User $user */
    $user = $this->getUser();

    $conversation = $this->conversationRepository->find($id);

    if (!$conversation) {
      return $this->json(['message' => 'Conversation not found'], Response::HTTP_NOT_FOUND);
    }

    if (!in_array($user, $conversation->getParticipants())) {
      return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $this->messageService->markMessagesAsRead($conversation, $user);

    return $this->json(['message' => 'Messages marked as read'], Response::HTTP_OK);
  }

  #[Route('/messages/unread-count', name: 'api_unread_messages_count', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Response(
    response: 200,
    description: 'Nombre de messages non lus',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'count', type: 'integer', example: 5)
      ]
    )
  )]
  public function getUnreadMessagesCount(): JsonResponse
  {
    /** @var User $user */
    $user = $this->getUser();

    $count = $this->messageService->countUnreadMessages($user);

    return $this->json(['count' => $count], Response::HTTP_OK);
  }

  #[Route('/messages/predefined', name: 'api_predefined_messages', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Response(
    response: 200,
    description: 'Liste des messages prédéfinis',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(type: 'string')
    )
  )]
  public function getPredefinedMessages(): JsonResponse
  {
    $predefinedMessages = $this->messageService->getPredefinedMessages();

    return $this->json($predefinedMessages, Response::HTTP_OK);
  }

  #[Route('/user/messages', name: 'api_user_messages', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Response(
    response: 200,
    description: 'Point de terminaison SSE pour les messages de l\'utilisateur',
    content: new OA\MediaType(mediaType: 'text/event-stream')
  )]
  public function userMessagesStream(): Response
  {
    return new Response('', Response::HTTP_OK);
  }
}
