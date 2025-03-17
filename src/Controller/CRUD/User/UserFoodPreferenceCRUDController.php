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
use Dom\Entity;
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
class UserFoodPreferenceCRUDController extends AbstractController
{
    #[Route('/food-preferences', name: 'api_profile_food_preferences_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\RequestBody(
        description: 'IDs des préférences alimentaires à associer au profil',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'preferenceIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3])
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Préférences alimentaires mises à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'foodPreferences', type: 'array', items: new OA\Items(ref: new Model(type: FoodPreference::class, groups: ['food_preference:read'])))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    public function updateFoodPreferences(
        Request $request,
        EntityManagerInterface $em,
        FoodPreferenceRepository $foodPreferenceRepository
        ): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user || !$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['preferenceIds']) || !is_array($data['preferenceIds'])) {
            return $this->json(['error' => 'Le champ preferenceIds est requis et doit être un tableau'], Response::HTTP_BAD_REQUEST);
        }
        
        $preferenceIds = $data['preferenceIds'];

        foreach ($user->getFoodPreference() as $preference) {
            $user->removeFoodPreference($preference);
        }

        foreach ($preferenceIds as $id) {
            $preference = $foodPreferenceRepository->find($id);
            if ($preference) {
                $user->addFoodPreference($preference);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Préférences alimentaires mises à jour avec succès',
            'foodPreferences' => $user->getFoodPreference()
        ], Response::HTTP_OK, [], [
            'groups' => ['food_preference:read']
        ]);
    }

    #[Route('/food-preferences/list', name: 'api_food_preferences_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste de toutes les préférences alimentaires disponibles',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: FoodPreference::class, groups: ['food_preference:read']))
        )
    )]
    public function getFoodPreferences(
        TagAwareCacheInterface $cachePool,
        FoodPreferenceRepository $foodPreferenceRepository
    ): JsonResponse
    {
        $cacheKey = 'food_preferences_list';
        
        $preferences = $cachePool->get($cacheKey, function (ItemInterface $item) use ($foodPreferenceRepository) {
            $item->tag(['foodPreferencesCache']);
            $item->expiresAfter(3600);
            
            return $foodPreferenceRepository->findAll();
        });
        
        return $this->json($preferences, Response::HTTP_OK, [], [
            'groups' => ['food_preference:read']
        ]);
    }
}