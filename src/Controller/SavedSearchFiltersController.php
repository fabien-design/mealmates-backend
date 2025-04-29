<?php

namespace App\Controller;

use App\Entity\SavedSearchFilters;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/v1/saved-searches')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Recherches sauvegardées')]
class SavedSearchFiltersController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'api_saved_searches_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste des recherches sauvegardées',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'savedSearches', type: 'array', items: new OA\Items())
            ]
        )
    )]
    public function listSavedSearches(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $savedSearches = $user->getSavedSearchFilters();
        
        return $this->json([
            'savedSearches' => $savedSearches
        ], Response::HTTP_OK, [], ['groups' => ['saved_search:read']]);
    }

    #[Route('', name: 'api_saved_searches_create', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Données pour créer une recherche sauvegardée',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'Nom de la recherche sauvegardée'),
                new OA\Property(property: 'latitude', type: 'number', format: 'float', description: 'Latitude du point de recherche'),
                new OA\Property(property: 'longitude', type: 'number', format: 'float', description: 'Longitude du point de recherche'),
                new OA\Property(
                    property: 'filters',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'productTypes',
                            type: 'array',
                            items: new OA\Items(type: 'string', enum: ['fruits', 'grocery', 'vegetables', 'dairy', 'meals'])
                        ),
                        new OA\Property(
                            property: 'dietaryPreferences',
                            type: 'array',
                            items: new OA\Items(type: 'string')
                        ),
                        new OA\Property(
                            property: 'expirationDate',
                            type: 'string',
                            enum: ['today', 'tomorrow', 'week']
                        ),
                        new OA\Property(
                            property: 'distance',
                            type: 'integer',
                            description: 'Rayon de recherche en mètres'
                        ),
                        new OA\Property(
                            property: 'price',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'min', type: 'number', format: 'float'),
                                new OA\Property(property: 'max', type: 'number', format: 'float')
                            ]
                        ),
                        new OA\Property(
                            property: 'minSellerRating',
                            type: 'number',
                            format: 'float',
                            minimum: 0,
                            maximum: 5
                        )
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Recherche sauvegardée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'savedSearch', type: 'object')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Erreur de validation ou limite de recherches atteinte',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'errors', type: 'object', nullable: true)
            ]
        )
    )]
    public function createSavedSearch(
        Request $request, 
        SerializerInterface $serializer, 
        EntityManagerInterface $entityManager, 
        ValidatorInterface $validator
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
    
        if ($user->getSavedSearchCount() >= 3) {
            return $this->json([
                'success' => false,
                'message' => 'Vous avez atteint la limite de 3 recherches sauvegardées.'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $savedSearch = new SavedSearchFilters();
            $savedSearch->setUser($user);

            $context = [
                'object_to_populate' => $savedSearch,
                'groups' => ['saved_search:write'],
                'disable_type_enforcement' => true,
                'ignored_attributes' => ['user'] // Sécurité supp (d'apres claude.ai)
            ];
            
            $data = json_decode($request->getContent(), true);

            if (isset($data['filters'])) {
                $filters = $data['filters'];

                if (isset($filters['distance'])) {
                    $data['radius'] = $filters['distance'];
                }
                
                if (isset($filters['productTypes'])) {
                    $data['productTypes'] = $filters['productTypes'];
                }
                
                if (isset($filters['expirationDate'])) {
                    $data['expirationDate'] = $filters['expirationDate'];
                }
                
                if (isset($filters['price']['min'])) {
                    $data['minPrice'] = $filters['price']['min'];
                }
                
                if (isset($filters['price']['max'])) {
                    $data['maxPrice'] = $filters['price']['max'];
                }
                
                if (isset($filters['minSellerRating'])) {
                    $data['minSellerRating'] = $filters['minSellerRating'];
                }
                
                if (isset($filters['dietaryPreferences'])) {
                    $data['dietaryPreferences'] = $filters['dietaryPreferences'];
                }
            }

            $serializer->deserialize(
                json_encode($data),
                SavedSearchFilters::class,
                'json',
                $context
            );
            
            $errors = $validator->validate($savedSearch);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $errorMessages
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $entityManager->persist($savedSearch);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Recherche sauvegardée avec succès',
                'savedSearch' => $savedSearch
            ], Response::HTTP_CREATED, [], ['groups' => ['saved_search:read']]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la désérialisation: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_saved_searches_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'une recherche sauvegardée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'savedSearch', type: 'object')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès non autorisé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Recherche non trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    public function getSavedSearch(SavedSearchFilters $savedSearch): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if ($savedSearch->getUser() !== $user) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à accéder à cette recherche'
            ], Response::HTTP_FORBIDDEN);
        }
        
        return $this->json([
            'savedSearch' => $savedSearch
        ], Response::HTTP_OK, [], ['groups' => ['saved_search:read']]);
    }

    #[Route('/{id}', name: 'api_saved_searches_delete', methods: ['DELETE'])]
    #[OA\Response(
        response: 200,
        description: 'Recherche supprimée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès non autorisé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    public function deleteSavedSearch(SavedSearchFilters $savedSearch): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if ($savedSearch->getUser() !== $user) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette recherche'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $this->entityManager->remove($savedSearch);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Recherche supprimée avec succès'
        ], Response::HTTP_OK);
    }
}
