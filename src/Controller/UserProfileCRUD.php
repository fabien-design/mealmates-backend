<?php

namespace App\Controller;

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[Route('/api/v1/user')]
#[OA\Tag(name: 'Utilisateur')]
class UserProfileCRUD extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TagAwareCacheInterface $cache
    ) {
    }

    #[Route('/logged', name: 'api_profile_me', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Retourne success si l\'utilisateur est connecté',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true)
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Accès refusé si l\'utilisateur n\'est pas connecté',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
            ]
        )
    )]
    public function logged(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(["success" => !empty($user)], Response::HTTP_OK, []);
    }

    #[Route('', name: 'api_profile_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Retourne le profil complet de l\'utilisateur avec toutes ses informations associées',
        content: new Model(type: User::class, groups: ['user:read', 'user:profile', 'address:read', 'allergen:read', 'food_preference:read'])
    )]
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        return $this->json($user, Response::HTTP_OK, [], [
            'groups' => ['user:read', 'user:profile', 'address:read', 'allergen:read', 'food_preference:read']
        ]);
    }

    #[Route('/update', name: 'api_profile_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\RequestBody(
        description: 'Données pour mettre à jour le profil complet',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'sexe', type: 'boolean', example: true),
                new OA\Property(property: 'addresses', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true, description: 'ID de l\'adresse existante (null pour une nouvelle adresse)'),
                        new OA\Property(property: 'address', type: 'string'),
                        new OA\Property(property: 'city', type: 'string'),
                        new OA\Property(property: 'zipCode', type: 'string'),
                        new OA\Property(property: 'region', type: 'string'),
                        new OA\Property(property: 'latitude', type: 'number', nullable: true),
                        new OA\Property(property: 'longitude', type: 'number', nullable: true)
                    ]
                )),
                new OA\Property(property: 'allergenIds', type: 'array', items: new OA\Items(type: 'integer')),
                new OA\Property(property: 'foodPreferenceIds', type: 'array', items: new OA\Items(type: 'integer'))
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Profil mis à jour avec succès.',
        content: new Model(type: User::class, groups: ['user:read', 'user:profile', 'address:read', 'allergen:read', 'food_preference:read'])
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    public function update(
        Request $request,
        AllergenRepository $allergenRepository,
        FoodPreferenceRepository $foodPreferenceRepository,
        AddressRepository $addressRepository,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $content = $request->getContent();
        $data = json_decode($content, true);

        $serializer->deserialize($content, User::class, 'json', [
            'object_to_populate' => $user,
            'groups' => ['user:write']
        ]);

        // Handle addresses update
        if (isset($data['addresses']) && is_array($data['addresses'])) {
            $submittedAddressIds = [];

            foreach ($data['addresses'] as $addressData) {
                if (isset($addressData['id']) && $addressData['id']) {
                    // Update existing address
                    $submittedAddressIds[] = $addressData['id'];
                    $existingAddress = $addressRepository->find($addressData['id']);

                    if ($existingAddress && $existingAddress->getIdUser()->contains($user)) {
                        $existingAddress->setAddress($addressData['address'] ?? $existingAddress->getAddress());
                        $existingAddress->setCity($addressData['city'] ?? $existingAddress->getCity());
                        $existingAddress->setZipCode($addressData['zipCode'] ?? $existingAddress->getZipCode());
                        $existingAddress->setRegion($addressData['region'] ?? $existingAddress->getRegion());
                        $existingAddress->setLatitude($addressData['latitude'] ?? $existingAddress->getLatitude());
                        $existingAddress->setLongitude($addressData['longitude'] ?? $existingAddress->getLongitude());
                    }
                } else {
                    // Create new address
                    $address = new Address();
                    $address->setAddress($addressData['address'] ?? null);
                    $address->setCity($addressData['city'] ?? null);
                    $address->setZipCode($addressData['zipCode'] ?? null);
                    $address->setRegion($addressData['region'] ?? null);
                    $address->setLatitude($addressData['latitude'] ?? null);
                    $address->setLongitude($addressData['longitude'] ?? null);

                    $address->addIdUser($user);
                    $user->addAddress($address);

                    $this->em->persist($address);
                    $submittedAddressIds[] = $address->getId();
                }
            }
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'user' => $user
        ], Response::HTTP_OK, [], [
            'groups' => ['user:read', 'user:profile', 'address:read', 'allergen:read', 'food_preference:read']
        ]);
    }

    #[Route('/address', name: 'api_profile_address_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\RequestBody(
        description: 'Données pour ajouter une nouvelle adresse',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'address', type: 'string', example: '123 Main Street'),
                new OA\Property(property: 'zipCode', type: 'string', example: '75000'),
                new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                new OA\Property(property: 'region', type: 'string', example: 'Île-de-France')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Adresse ajoutée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:profile', 'address:read']))
            ]
        )
    )]
    public function addAddress(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $data = json_decode($request->getContent(), true);

        $address = new Address();
        $address->setAddress($data['address']);
        $address->setZipCode($data['zipCode']);
        $address->setCity($data['city']);
        $address->setRegion($data['region']);

        $errors = $this->validator->validate($address);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $address->addIdUser($user);
        $user->addAddress($address);

        $this->em->persist($address);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Adresse ajoutée avec succès',
            'user' => $user
        ], Response::HTTP_CREATED, [], [
            'groups' => ['user:profile', 'address:read']
        ]);
    }

    #[Route('/address/{id}', name: 'api_profile_address_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Adresse supprimée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:profile', 'address:read']))
            ]
        )
    )]
    public function deleteAddress(int $id, AddressRepository $addressRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $address = $addressRepository->find($id);

        if (!$address) {
            throw new NotFoundHttpException('Adresse non trouvée');
        }

        if (!$address->getIdUser()->contains($user)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à supprimer cette adresse');
        }

        $user->removeAddress($address);
        $address->removeIdUser($user);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Adresse supprimée avec succès',
            'user' => $user
        ], Response::HTTP_OK, [], [
            'groups' => ['user:profile', 'address:read']
        ]);
    }
}