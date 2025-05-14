<?php

namespace App\Controller;

use App\Entity\FoodPreference;
use App\Repository\FoodPreferenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/v1/food-preferences')]
#[OA\Tag(name: 'Préférences Alimentaires')]
class FoodPreferenceController extends AbstractController
{
    #[Route('', name: 'api_food_preferences_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste de toutes les préférences alimentaires',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
            ])
        )
    )]
    public function list(FoodPreferenceRepository $foodPreferenceRepository): JsonResponse
    {
        $preferences = $foodPreferenceRepository->findAll();
        return $this->json($preferences, Response::HTTP_OK, [], ['groups' => ['food_preference:read']]);
    }

    #[Route('/{id}', name: 'api_food_preference_show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'une préférence alimentaire',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Préférence alimentaire non trouvée'
    )]
    public function show(int $id, FoodPreferenceRepository $foodPreferenceRepository): JsonResponse
    {
        $preference = $foodPreferenceRepository->find($id);

        if (!$preference) {
            return $this->json(['message' => 'Préférence alimentaire non trouvée'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($preference, Response::HTTP_OK, [], ['groups' => ['food_preference:read']]);
    }
}
