<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MercureTokenService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/mercure')]
#[OA\Tag(name: 'Mercure')]
class MercureController extends AbstractController
{
    public function __construct(
        private MercureTokenService $mercureTokenService
    ) {
    }

    #[Route('/token', name: 'api_mercure_token', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Jeton JWT pour s\'abonner aux sujets Mercure',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')
            ]
        )
    )]
    public function getToken(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $token = $this->mercureTokenService->generateUserSubscriptionToken($user);
        
        return $this->json(['token' => $token], Response::HTTP_OK);
    }
}
