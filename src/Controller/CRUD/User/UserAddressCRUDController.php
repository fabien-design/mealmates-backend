<?php

namespace App\Controller\CRUD\User;

use App\Entity\User;
use App\Entity\Address;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[Route('/api/v1/user')]
#[OA\Tag(name: 'Utilisateur')]
class UserAddressCRUDController extends AbstractController
{
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
                new OA\Property(property: 'address', ref: new Model(type: Address::class, groups: ['address:read']))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    public function add(
        Request $request,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user || !$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }

        $address = $serializer->deserialize($request->getContent(), Address::class, 'json', [
            'groups' => ['address:write']
        ]);

        $errors = $validator->validate($address);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        
        $address->addIdUser($user);
        $user->addAddress($address);

        $em->persist($address);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Adresse ajoutée avec succès',
            'address' => $address
        ], Response::HTTP_CREATED, [], [
            'groups' => ['address:read']
        ]);
    }

    #[Route('/address/{id}', name: 'api_profile_address_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID de l\'adresse à supprimer',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Adresse supprimée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès refusé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Adresse non trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string')
            ]
        )
    )]
    public function delete(
        Address $address,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user || !$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour accéder à cette ressource.');
        }
        
        if (!$address) {
            return $this->json([
                'error' => 'Adresse non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$address->getIdUser()->contains($user)) {
            return $this->json([
                'error' => 'Vous n\'êtes pas autorisé à supprimer cette adresse'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->removeAddress($address);
        $address->removeIdUser($user);
        
        if ($address->getIdUser()->isEmpty()) {
            $em->remove($address);
        }
        
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Adresse supprimée avec succès'
        ]);
    }
}