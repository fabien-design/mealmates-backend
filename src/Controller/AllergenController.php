<?php

namespace App\Controller;

use App\Entity\Allergen;
use App\Repository\AllergenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/v1/allergens')]
#[OA\Tag(name: 'Allergènes')]
class AllergenController extends AbstractController
{
    #[Route('', name: 'api_allergens_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste de tous les allergènes',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string')
            ])
        )
    )]
    public function list(AllergenRepository $allergenRepository): JsonResponse
    {
        $allergens = $allergenRepository->findAll();
        return $this->json($allergens, Response::HTTP_OK, [], ['groups' => ['allergen:read']]);
    }

    #[Route('/{id}', name: 'api_allergen_show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'un allergène',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Allergène non trouvé'
    )]
    public function show(int $id, AllergenRepository $allergenRepository): JsonResponse
    {
        $allergen = $allergenRepository->find($id);

        if (!$allergen) {
            return $this->json(['message' => 'Allergène non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($allergen, Response::HTTP_OK, [], ['groups' => ['allergen:read']]);
    }
}
