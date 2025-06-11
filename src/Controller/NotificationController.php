<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[Route('/api/v1/notifications')]
#[OA\Tag(name: 'Notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $notificationRepository
    ) {
    }

    #[Route('', name: 'api_notifications_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre maximum de notifications à retourner',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)
    )]
    #[OA\Parameter(
        name: 'offset',
        description: 'Décalage pour la pagination',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 0, minimum: 0)
    )]
    #[OA\Parameter(
        name: 'unread_only',
        description: 'Afficher uniquement les notifications non lues',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des notifications de l\'utilisateur connecté',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: Notification::class))),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'unread_count', type: 'integer')
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $limit = min((int) $request->query->get('limit', 20), 100);
        $offset = max((int) $request->query->get('offset', 0), 0);
        $unreadOnly = $request->query->getBoolean('unread', false);

        $notifications = $this->notificationRepository->findByUserWithPagination($user, $limit, $offset, $unreadOnly);
        $totalCount = $this->notificationRepository->countByUser($user);
        $unreadCount = $this->notificationRepository->countUnreadByUser($user);

        return $this->json([
            'success' => true,
            'data' => $notifications,
            'total' => $totalCount,
            'unread_count' => $unreadCount
        ], Response::HTTP_OK);
    }

    #[Route('/{id}/mark-as-read', name: 'api_notifications_mark_as_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Notification marquée comme lue avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'notification', ref: new Model(type: Notification::class))
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Notification non trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Notification non trouvée')
            ]
        )
    )]
    public function markAsRead(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            throw new NotFoundHttpException('Notification non trouvée');
        }

        if ($notification->getUser() !== $user) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à modifier cette notification');
        }

        $notification->setIsRead(true);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification marquée comme lue avec succès',
            'notification' => $notification
        ], Response::HTTP_OK);
    }

    #[Route('/mark-all-as-read', name: 'api_notifications_mark_all_as_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Toutes les notifications marquées comme lues avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'updated_count', type: 'integer')
            ]
        )
    )]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $updatedCount = $this->notificationRepository->markAllAsReadByUser($user);

        return $this->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'updated_count' => $updatedCount
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Notification supprimée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Notification non trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Notification non trouvée')
            ]
        )
    )]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            throw new NotFoundHttpException('Notification non trouvée');
        }

        if ($notification->getUser() !== $user) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à supprimer cette notification');
        }

        $this->em->remove($notification);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification supprimée avec succès'
        ], Response::HTTP_OK);
    }
}
