<?php

namespace App\Controller\CRUD\User;

use App\Entity\User;
use App\Entity\Address;
use App\Entity\Allergen;
use App\Entity\FoodPreference;
use App\Repository\UserRepository;
use App\Repository\AddressRepository;
use App\Repository\AllergenRepository;
use App\Repository\FoodPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[Route('/api/v1/user')]
#[OA\Tag(name: 'Utilisateur')]
class UserAllergenCRUDController extends AbstractController
{
    #[Route('/allergens', name: 'api_profile_allergens_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\RequestBody(
        description: 'IDs des allergènes à associer au profil',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'allergenIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3])
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Allergènes mis à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'allergens', type: 'array', items: new OA\Items(ref: new Model(type: Allergen::class, groups: ['allergen:read'])))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    public function update(
        Request $request,
        EntityManagerInterface $em,
        AllergenRepository $allergenRepository
    ): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user || !$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['allergenIds']) || !is_array($data['allergenIds'])) {
            return $this->json(['error' => 'Le champ allergenIds est requis et doit être un tableau'], Response::HTTP_BAD_REQUEST);
        }
        
        $allergenIds = $data['allergenIds'];

        foreach ($user->getAllergen() as $allergen) {
            $user->removeAllergen($allergen);
        }

        foreach ($allergenIds as $id) {
            $allergen = $allergenRepository->find($id);
            if ($allergen) {
                $user->addAllergen($allergen);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Allergènes mis à jour avec succès',
            'allergens' => $user->getAllergen()
        ], Response::HTTP_OK, [], [
            'groups' => ['allergen:read']
        ]);
    }

    #[Route('/allergens/list', name: 'api_allergens_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste de tous les allergènes disponibles',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Allergen::class, groups: ['allergen:read']))
        )
    )]
    public function getAllergens(
        TagAwareCacheInterface $cachePool,
        AllergenRepository $allergenRepository
    ): JsonResponse
    {
        $cacheKey = 'allergens_list';
        
        $allergens = $cachePool->get($cacheKey, function (ItemInterface $item) use ($allergenRepository) {
            $item->tag(['allergensCache']);
            $item->expiresAfter(3600);
            
            return $allergenRepository->findAll();
        });
        
        return $this->json($allergens, Response::HTTP_OK, [], [
            'groups' => ['allergen:read']
        ]);
    }
}