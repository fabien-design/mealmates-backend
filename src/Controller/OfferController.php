<?php

namespace App\Controller;

use App\Repository\AddressRepository;
use App\Repository\AllergenRepository;
use App\Repository\FoodPreferenceRepository;
use App\Repository\OfferRepository;
use App\Enums\OfferStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Offer;
use App\Entity\Image;
use App\Enums\ImageExtension;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
class OfferController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }


    #[Route('/products/nearby', name: 'api_products_nearby', methods: ['GET'])]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'lat', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'lng', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'radius', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'types', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'expirationDate', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['today', 'tomorrow', 'week']))]
    #[OA\Parameter(name: 'minPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'maxPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'dietaryPreferences', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    public function getNearbyProducts(Request $request, OfferRepository $offerRepository): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius');

        if (!$lat || !$lng || !$radius) {
            return $this->json([
                'error' => 'Les paramètres lat, lng et radius sont obligatoires'
            ], Response::HTTP_BAD_REQUEST);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = (float) $radius;

        $filters = [];

        if ($request->query->has('types')) {
            $filters['productTypes'] = explode(',', $request->query->get('types'));
        }

        if ($request->query->has('expirationDate')) {
            $filters['expirationDate'] = $request->query->get('expirationDate');
        }

        if ($request->query->has('minPrice')) {
            $filters['minPrice'] = (float) $request->query->get('minPrice');
        }

        if ($request->query->has('maxPrice')) {
            $filters['maxPrice'] = (float) $request->query->get('maxPrice');
        }

        if ($request->query->has('dietaryPreferences')) {
            $filters['dietaryPreferences'] = explode(',', $request->query->get('dietaryPreferences'));
        }

        $offers = $offerRepository->findNearbyOffers($lat, $lng, $radius, $filters);

        return $this->json($offers, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }

    #[Route('/products/add', name: 'api_add_product', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Tag(name: 'Offres')]
    #[OA\RequestBody(
        description: 'Données pour créer une nouvelle offre',
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                required: ['name', 'description', 'price', 'quantity', 'expiryDate', 'address'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Légumes du jardin'),
                    new OA\Property(property: 'description', type: 'string', example: 'Surplus de ma récolte'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 15.50),
                    new OA\Property(property: 'quantity', type: 'integer', example: 3),
                    new OA\Property(property: 'expiryDate', type: 'string', format: 'date', example: '2025-06-15'),
                    new OA\Property(property: 'isRecurring', type: 'boolean', example: false),
                    new OA\Property(property: 'address', type: 'integer', example: 1),
                    new OA\Property(property: 'allergens', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'food_preferences', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(
                        property: 'images',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'data', type: 'string', description: 'Base64 encoded image'),
                                new OA\Property(property: 'mimeType', type: 'string')
                            ]
                        )
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Offre créée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offre créée avec succès'),
                new OA\Property(property: 'offer', type: 'object')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'errors', type: 'object')
            ]
        )
    )]
    public function addProduct(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        AllergenRepository $allergenRepository,
        FoodPreferenceRepository $foodPreferenceRepository,
        AddressRepository $addressRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (empty($data)) {
            return $this->json([
                'success' => false,
                'error' => 'Aucune donnée fournie'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $offer = new Offer();
            $serializer->deserialize(
                $request->getContent(),
                Offer::class,
                JsonEncoder::FORMAT,
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $offer,
                    AbstractNormalizer::GROUPS => ['offer:write'],
                ]
            );

            $offer->setSeller($this->getUser());
            $offer->setDynamicPrice($offer->getPrice());
            $offer->setBuyer(null);

            if (isset($data['allergens']) && is_array($data['allergens']) && count($data['allergens']) > 0) {
                foreach ($data['allergens'] as $allergenId) {
                    $allergen = $allergenRepository->find($allergenId);
                    if ($allergen) {
                        $offer->addAllergen($allergen);
                    }
                }
            }

            if (isset($data['food_preferences']) && is_array($data['food_preferences']) && count($data['food_preferences']) > 0) {
                foreach ($data['food_preferences'] as $foodPreferenceId) {
                    $foodPreference = $foodPreferenceRepository->find($foodPreferenceId);
                    if ($foodPreference) {
                        $offer->addFoodPreference($foodPreference);
                    }
                }
            }

            if (isset($data['address']) && $data['address'] !== null) {
                $address = $addressRepository->find($data['address']);
                if (!$address) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Adresse non trouvée'
                    ], Response::HTTP_BAD_REQUEST);
                }
                $offer->setAddress($address);
            } else {
                return $this->json([
                    'success' => false,
                    'error' => 'Adresse obligatoire'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imageData) {
                    if (empty($imageData['data']) || empty($imageData['name'])) {
                        continue;
                    }

                    try {
                        $base64Image = $imageData['data'];
                        if (str_contains($base64Image, ',')) {
                            $base64Image = explode(',', $base64Image)[1];
                        }

                        $imageContent = base64_decode($base64Image);
                        if ($imageContent === false) {
                            continue;
                        }

                        $extension = strtolower(pathinfo($imageData['name'], PATHINFO_EXTENSION));
                        if (ImageExtension::tryFrom($extension) === null) {
                            $this->logger->warning("Extension d'image non supportée: $extension");
                            continue;
                        }

                        $tempFile = tempnam(sys_get_temp_dir(), 'upload') . '.' . $extension;
                        file_put_contents($tempFile, $imageContent);

                        $image = new Image();
                        $uploadedFile = new UploadedFile(
                            $tempFile,
                            $imageData['name'],
                            $imageData['mimeType'] ?? 'image/' . $extension,
                            null,
                            true
                        );
                        $image->setFile($uploadedFile);
                        $image->setCreatedAt(new \DateTimeImmutable());
                        $image->setUpdatedAt(new \DateTimeImmutable());
                        $image->setOffer($offer);

                        $em->persist($image);
                    } catch (\Exception $e) {
                        $this->logger->error("Erreur lors du traitement de l'image: " . $e->getMessage());
                        error_log("Erreur lors du traitement de l'image: " . $e->getMessage());
                        continue;
                    }
                }
            }

            $errors = $validator->validate($offer);
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

            $em->persist($offer);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Offre créée avec succès',
                'offer' => $offer
            ], Response::HTTP_CREATED, [], [
                'groups' => ['offer:read', 'image:read'],
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la création de l'offre: " . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'offre',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    #[Route('/products/my-offers', name: 'api_my_offers', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(
        name: 'status',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['active', 'sold', 'expired', 'all']),
        description: 'Filtrer par statut'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des offres de l\'utilisateur'
    )]
    public function getMyOffers(Request $request, OfferRepository $offerRepository): JsonResponse
    {
        $user = $this->getUser();
        $status = $request->query->get('status', 'all');

        if ($status !== 'all' && !in_array(OfferStatus::tryFrom($status), OfferStatus::cases())) {
            return $this->json([
                'error' => 'Statut invalide. Utilisez "active", "expired", "sold", "all".'
            ], Response::HTTP_BAD_REQUEST);
        }

        $offers = $offerRepository->findUserOffersByStatus($user, $status);

        return $this->json($offers, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }

    #[Route('/products/bought-offers', name: 'api_bought_offers', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Liste des offres achetées par l\'utilisateur',
    )]
    #[OA\Tag(name: 'Offres')]
    public function getBoughtOffers(OfferRepository $offerRepository): JsonResponse
    {
        $user = $this->getUser();
        $offers = $offerRepository->findUserBoughtOffers($user);

        return $this->json($offers, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }

    #[Route('/products/{id}', name: 'api_get_product', methods: ['GET'])]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Détails de l\'offre',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Response(
        response: 404,
        description: 'Offre non trouvée'
    )]
    public function getProduct(Offer $offer): JsonResponse
    {
        return $this->json($offer, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }

    #[Route('/products/{id}/donate', name: 'api_donate_product', methods: ['PATCH'])]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Offre convertie en don avec succès',
        content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'success', type: 'boolean'),
            new OA\Property(property: 'offer', type: 'object')
        ])
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès refusé',
        content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'error', type: 'string')
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Offre non trouvée',
        content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'error', type: 'string')
        ])
    )]
    public function donateProduct(Offer $offer, EntityManagerInterface $em): JsonResponse
    {
        if ($offer->getSeller() !== $this->getUser()) {
            return $this->json([
                'error' => 'Vous n\'êtes pas autorisé à modifier cette offre'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($offer->getBuyer() !== null) {
            return $this->json([
                'error' => 'Cette offre a déjà été vendue'
            ], Response::HTTP_BAD_REQUEST);
        }

        $offer->setPrice(0);
        $offer->setDynamicPrice(0);

        $em->flush();

        return $this->json([
            'success' => true,
            'offer' => $offer,
            'message' => 'Offre convertie en don avec succès',
        ], Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }

    #[Route('/products/{id}/edit', name: 'api_edit_product', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        description: 'Données pour modifier l\'offre',
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float'),
                    new OA\Property(property: 'quantity', type: 'integer'),
                    new OA\Property(property: 'expiryDate', type: 'string', format: 'date'),
                    new OA\Property(property: 'isRecurring', type: 'boolean')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Offre modifiée avec succès'
    )]
    #[OA\Response(
        response: 403,
        description: 'Vous n\'êtes pas autorisé à modifier cette offre'
    )]
    #[OA\Response(
        response: 404,
        description: 'Offre non trouvée'
    )]
    public function editProduct(
        Offer $offer,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($offer->getSeller() !== $this->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette offre'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($offer->getSoldAt() !== null) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de modifier une offre déjà vendue'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $serializer->deserialize(
                $request->getContent(),
                Offer::class,
                JsonEncoder::FORMAT,
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $offer,
                    AbstractNormalizer::GROUPS => ['offer:write'],
                ]
            );

            $errors = $validator->validate($offer);
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

            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Offre modifiée avec succès',
                'offer' => $offer
            ], Response::HTTP_OK, [], [
                'groups' => ['offer:read'],
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la modification de l'offre: " . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'offre',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/products/{id}/delete', name: 'api_delete_product', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Offre supprimée avec succès'
    )]
    #[OA\Response(
        response: 403,
        description: 'Vous n\'êtes pas autorisé à supprimer cette offre'
    )]
    public function deleteProduct(Offer $offer, EntityManagerInterface $em): JsonResponse
    {
        if ($offer->getSeller() !== $this->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette offre'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($offer->getSoldAt() !== null) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer une offre déjà vendue'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $em->remove($offer);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Offre supprimée avec succès'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression de l'offre: " . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'offre',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
