<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notification;
use App\Enums\NotificationType;
use App\Repository\BadgeRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserProgressRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Gamification')]
class GamificationController extends AbstractController
{
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly BadgeRepository $badgeRepository,
    private readonly UserBadgeRepository $userBadgeRepository,
    private readonly UserProgressRepository $userProgressRepository,
    private readonly UserRepository $userRepository,
    private readonly NotificationRepository $notificationRepository
  ) {}

  #[Route('/users/{id}/badges', name: 'api_user_badges', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'L\'id de l\'utilisateur',
    in: 'path',
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Liste des badges de l\'utilisateur',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(
        properties: [
          new OA\Property(property: 'id', type: 'integer'),
          new OA\Property(property: 'name', type: 'string'),
          new OA\Property(property: 'description', type: 'string'),
          new OA\Property(property: 'imageUrl', type: 'string'),
          new OA\Property(property: 'category', type: 'string'),
          new OA\Property(property: 'tier', type: 'integer'),
          new OA\Property(property: 'unlockedAt', type: 'string', format: 'date-time', nullable: true),
          new OA\Property(property: 'isUnlocked', type: 'boolean')
        ]
      )
    )
  )]
  public function getUserBadges(int $id): JsonResponse
  {
    // Vérifier que l'utilisateur a le droit de voir ces informations
    /** @var User $currentUser */
    $currentUser = $this->getUser();
    if ($currentUser->getId() !== $id && !$this->isGranted('ROLE_ADMIN')) {
      return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
    }

    $allBadges = $this->badgeRepository->findAll();
    $userBadges = $user->getUserBadges();

    $badgesData = [];

    foreach ($allBadges as $badge) {
      $isUnlocked = false;
      $unlockedAt = null;

      foreach ($userBadges as $userBadge) {
        if ($userBadge->getBadge()->getId() === $badge->getId()) {
          $isUnlocked = true;
          $unlockedAt = $userBadge->getAwardedAt()?->format('c');
          break;
        }
      }

      $badgesData[] = [
        'id' => $badge->getId(),
        'name' => $badge->getName(),
        'description' => $badge->getDescription(),
        'icon' => $badge->getIcon(),
        'category' => $badge->getType(),
        'tier' => $badge->getThreshold() ? max(1, (int)($badge->getThreshold() / 10)) : 1,
        'unlockedAt' => $unlockedAt,
        'isUnlocked' => $isUnlocked
      ];
    }

    return $this->json($badgesData, Response::HTTP_OK);
  }

  #[Route('/users/{id}/progress', name: 'api_user_progress', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'L\'id de l\'utilisateur',
    in: 'path',
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Progression de l\'utilisateur',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(
        properties: [
          new OA\Property(property: 'id', type: 'string'),
          new OA\Property(property: 'name', type: 'string'),
          new OA\Property(property: 'currentValue', type: 'integer'),
          new OA\Property(property: 'targetValue', type: 'integer'),
          new OA\Property(property: 'unit', type: 'string'),
          new OA\Property(property: 'percentage', type: 'number', format: 'float'),
          new OA\Property(property: 'nextBadgeId', type: 'integer', nullable: true),
          new OA\Property(property: 'nextBadgeName', type: 'string', nullable: true)
        ]
      )
    )
  )]
  public function getUserProgress(int $id): JsonResponse
  {
    // Vérifier que l'utilisateur a le droit de voir ces informations
    /** @var User $currentUser */
    $currentUser = $this->getUser();
    if ($currentUser->getId() !== $id && !$this->isGranted('ROLE_ADMIN')) {
      return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
    }

    $progressTrackers = $user->getProgressTrackers();
    $progressData = [];

    foreach ($progressTrackers as $progress) {
      $type = $progress->getProgressType();
      $currentValue = $progress->getCurrentValue();

      // Trouver le prochain badge pour ce type de progression
      $badges = $this->badgeRepository->findBy(['type' => $type], ['threshold' => 'ASC']);
      $nextBadge = null;

      foreach ($badges as $badge) {
        if ($badge->getThreshold() > $currentValue) {
          $nextBadge = $badge;
          break;
        }
      }

      $progressData[] = [
        'id' => $type,
        'name' => $this->getProgressTypeName($type),
        'currentValue' => $currentValue,
        'targetValue' => $nextBadge ? $nextBadge->getThreshold() : ($currentValue + 10),
        'unit' => $this->getProgressTypeUnit($type),
        'percentage' => $nextBadge ? min(100, ($currentValue / $nextBadge->getThreshold()) * 100) : 100,
        'nextBadgeId' => $nextBadge ? $nextBadge->getId() : null,
        'nextBadgeName' => $nextBadge ? $nextBadge->getName() : null
      ];
    }

    return $this->json($progressData, Response::HTTP_OK);
  }

  #[Route('/badges', name: 'api_badges', methods: ['GET'])]
  #[OA\Response(
    response: 200,
    description: 'Liste de tous les badges disponibles',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: new Model(type: \App\Entity\Badge::class))
    )
  )]
  public function getAllBadges(): JsonResponse
  {
    $badges = $this->badgeRepository->findAll();

    return $this->json(
      $badges,
      Response::HTTP_OK,
      [],
      ['groups' => ['badge:read']]
    );
  }

  #[Route('/users/{id}/credits', name: 'api_user_credits', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'L\'id de l\'utilisateur',
    in: 'path',
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Crédits de l\'utilisateur',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'balance', type: 'integer'),
        new OA\Property(property: 'lifetimeEarned', type: 'integer')
      ]
    )
  )]
  public function getUserCredits(int $id): JsonResponse
  {
    // Vérifier que l'utilisateur a le droit de voir ces informations
    /** @var User $currentUser */
    $currentUser = $this->getUser();
    if ($currentUser->getId() !== $id && !$this->isGranted('ROLE_ADMIN')) {
      return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
    }

    // Pour l'instant, nous ne suivons pas les crédits gagnés à vie
    // donc nous utilisons simplement le solde actuel
    $currentCredits = $user->getCredits();

    return $this->json([
      'balance' => $currentCredits,
      'lifetimeEarned' => $currentCredits // À remplacer par le suivi des crédits gagnés à vie quand disponible
    ], Response::HTTP_OK);
  }

  #[Route('/users/{id}/gamification-history', name: 'api_user_gamification_history', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'id',
    description: 'L\'id de l\'utilisateur',
    in: 'path',
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Parameter(
    name: 'limit',
    description: 'Nombre maximum de résultats à retourner',
    in: 'query',
    schema: new OA\Schema(type: 'integer', default: 10)
  )]
  #[OA\Parameter(
    name: 'offset',
    description: 'Offset pour la pagination',
    in: 'query',
    schema: new OA\Schema(type: 'integer', default: 0)
  )]
  #[OA\Response(
    response: 200,
    description: 'Historique de gamification de l\'utilisateur',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(
        properties: [
          new OA\Property(property: 'id', type: 'integer'),
          new OA\Property(property: 'type', type: 'string', enum: ['badge', 'credit']),
          new OA\Property(property: 'title', type: 'string'),
          new OA\Property(property: 'description', type: 'string'),
          new OA\Property(property: 'value', type: 'integer', nullable: true),
          new OA\Property(property: 'badgeId', type: 'integer', nullable: true),
          new OA\Property(property: 'badgeImageUrl', type: 'string', nullable: true),
          new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
          new OA\Property(property: 'isRead', type: 'boolean')
        ]
      )
    )
  )]
  public function getUserGamificationHistory(int $id, Request $request): JsonResponse
  {
    // Vérifier que l'utilisateur a le droit de voir ces informations
    /** @var User $currentUser */
    $currentUser = $this->getUser();
    if ($currentUser->getId() !== $id && !$this->isGranted('ROLE_ADMIN')) {
      return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
    }

    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
    }

    $limit = $request->query->getInt('limit', 10);
    $offset = $request->query->getInt('offset', 0);

    // Récupérer les notifications de type badge ou crédit
    $notifications = $this->notificationRepository->findGamificationNotifications(
      $user->getId(),
      [NotificationType::BADGE->value, NotificationType::CREDIT->value],
      $limit,
      $offset
    );

    $historyData = [];

    foreach ($notifications as $notification) {
      $content = $notification->getContent();
      $type = $notification->getType() === NotificationType::BADGE->value ? 'badge' : 'credit';

      $item = [
        'id' => $notification->getId(),
        'type' => $type,
        'title' => $content['title'] ?? '',
        'description' => $content['message'] ?? '',
        'createdAt' => $notification->getCreatedAt()->format('c'),
        'isRead' => $notification->isRead()
      ];

      if ($type === 'badge' && isset($content['badgeId'])) {
        $item['badgeId'] = $content['badgeId'];

        // Récupérer l'image du badge si disponible
        $badge = $this->badgeRepository->find($content['badgeId']);
        if ($badge) {
          $item['badgeIcon'] = $badge->getIcon();
        }
      }

      if ($type === 'credit' && isset($content['credits'])) {
        $item['value'] = $content['credits'];
      }

      $historyData[] = $item;
    }

    return $this->json($historyData, Response::HTTP_OK);
  }

  #[Route('/users/{userId}/award-badge/{badgeId}', name: 'api_award_badge', methods: ['POST'])]
  #[IsGranted('ROLE_USER')]
  #[OA\Parameter(
    name: 'userId',
    description: 'L\'id de l\'utilisateur',
    in: 'path',
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Parameter(
    name: 'badgeId',
    description: 'L\'id du badge à attribuer',
    in: 'path',
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Badge attribué avec succès',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'message', type: 'string')
      ]
    )
  )]
  public function awardBadgeToUser(int $userId, int $badgeId): JsonResponse
  {
    $user = $this->userRepository->find($userId);
    if (!$user) {
      return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
    }

    $badge = $this->badgeRepository->find($badgeId);
    if (!$badge) {
      return $this->json(['error' => 'Badge non trouvé'], Response::HTTP_NOT_FOUND);
    }

    // Vérifier si l'utilisateur a déjà ce badge
    $existingBadge = $this->userBadgeRepository->findOneBy([
      'user' => $user,
      'badge' => $badge
    ]);

    if ($existingBadge) {
      return $this->json([
        'success' => false,
        'message' => 'L\'utilisateur possède déjà ce badge'
      ], Response::HTTP_BAD_REQUEST);
    }

    // Créer une nouvelle attribution de badge
    $userBadge = new \App\Entity\UserBadge();
    $userBadge->setUser($user);
    $userBadge->setBadge($badge);
    
    // La date est automatiquement définie dans le constructeur de UserBadge

    // Persister l'attribution du badge
    $this->entityManager->persist($userBadge);
    
    // Créer une notification pour informer l'utilisateur
    $notification = new \App\Entity\Notification();
    $notification->setUser($user);
    $notification->setType(NotificationType::BADGE->value);
    $notification->setContent([
      'title' => 'Nouveau badge débloqué !',
      'message' => 'Félicitations ! Vous avez débloqué le badge : ' . $badge->getName(),
      'badgeId' => $badge->getId()
    ]);
    $notification->setIsRead(false);
    $notification->setCreatedAt(new \DateTimeImmutable());
    
    $this->entityManager->persist($notification);
    $this->entityManager->flush();

    return $this->json([
      'success' => true,
      'message' => 'Badge attribué avec succès à l\'utilisateur',
      'badge' => [
        'id' => $badge->getId(),
        'name' => $badge->getName(),
        'description' => $badge->getDescription(),
        'icon' => $badge->getIcon()
      ]
    ], Response::HTTP_OK);
  }

  private function getProgressTypeName(string $type): string
  {
    return match ($type) {
      'offers_created' => 'Offres publiées',
      'food_saved' => 'Nourriture sauvée',
      'transactions_completed' => 'Transactions effectuées',
      'reviews_received' => 'Avis reçus',
      'reviews_given' => 'Avis donnés',
      'consecutive_days' => 'Jours consécutifs de connexion',
      'referrals' => 'Parrainages',
      default => ucfirst(str_replace('_', ' ', $type))
    };
  }

  private function getProgressTypeUnit(string $type): string
  {
    return match ($type) {
      'food_saved' => 'kg',
      'consecutive_days' => 'jours',
      default => 'unités'
    };
  }
}