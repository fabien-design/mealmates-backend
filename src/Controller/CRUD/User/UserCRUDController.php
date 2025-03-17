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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[Route('/api/v1/user')]
#[OA\Tag(name: 'Utilisateur')]
class UserCRUDController extends AbstractController
{
    #[Route('', name: 'api_profile_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Retourne les informations du profil de l\'utilisateur',
        content: new Model(type: User::class, groups: ['user:read', 'user:profile'])
    )]
    public function show(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        return $this->json($user, Response::HTTP_OK, [], [
            'groups' => ['user:read', 'user:profile']
        ]);
    }

    #[Route('/update', name: 'api_profile_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\RequestBody(
        description: 'Données pour mettre à jour le profil',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'sexe', type: 'boolean', example: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Profil mis à jour avec succès',
        content: new Model(type: User::class, groups: ['user:read'])
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    public function update(
        Request $request,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        EntityManagerInterface $em
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $updatedUser = $serializer->deserialize($request->getContent(), User::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $user,
            'groups' => ['user:write']
        ]);

        $errors = $validator->validate($updatedUser);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => $user
        ], Response::HTTP_OK, [], [
            'groups' => ['user:read']
        ]);
    }
}