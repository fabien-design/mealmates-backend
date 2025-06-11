<?php

namespace App\Controller;

use App\Repository\AddressRepository;
use App\Repository\AllergenRepository;
use App\Repository\FoodPreferenceRepository;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManager;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api/v1')]
class OfferController extends AbstractController
{
    #[Route('/products/nearby', name: 'api_products_nearby', methods: ['GET'])]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'lat', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'lng', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'radius', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'types', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'expirationDate', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['today', 'tomorrow', 'week']))]
    #[OA\Parameter(name: 'minPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'maxPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float'))]
    // #[OA\Parameter(name: 'minSellerRating', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float'))]
    #[OA\Parameter(name: 'dietaryPreferences', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    public function getNearbyProducts(Request $request, OfferRepository $offerRepository, SerializerInterface $serializer): JsonResponse
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

        // if ($request->query->has('minSellerRating')) {
        //     $filters['minSellerRating'] = (float) $request->query->get('minSellerRating');
        // }

        if ($request->query->has('dietaryPreferences')) {
            $filters['dietaryPreferences'] = explode(',', $request->query->get('dietaryPreferences'));
        }

        $offers = $offerRepository->findNearbyOffers($lat, $lng, $radius, $filters);

        return $this->json($offers, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }

    #[Route('/products/add', name: 'api_add_product', methods: ['POST'])]
    #[OA\Tag(name: 'Offres')]
    public function addProduct(Request $request, SerializerInterface $serializer, ValidatorInterface $validator, AllergenRepository $allergenRepository, FoodPreferenceRepository $foodPreferenceRepository, AddressRepository $addressRepository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data)) {
            return $this->json([
                'error' => 'Aucune donnée fournie'
            ], Response::HTTP_BAD_REQUEST);
        }

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
        if (count($data['allergens']) > 0) {
            foreach ($data['allergens'] as $allergen) {
                $offer->addAllergen($allergenRepository->find($allergen));
            }
        }
        if (count($data['food_preferences']) > 0) {
            foreach ($data['food_preferences'] as $foodPreference) {
                $offer->addFoodPreference($foodPreferenceRepository->find($foodPreference));
            }
        }
        if ($data['address'] !== null) {
            $offer->setAddress($addressRepository->find($data['address']));
        } else {
            return $this->json([
                'error' => 'Adresse non trouvée'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Handle image uploads
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $imageData) {
                if (empty($imageData['data']) || empty($imageData['name'])) {
                    continue;
                }

                // Remove any base64 image header
                $base64Image = $imageData['data'];
                if (str_contains($base64Image, ',')) {
                    $base64Image = explode(',', $base64Image)[1];
                }

                // Decode base64 image
                $imageContent = base64_decode($base64Image);
                if ($imageContent === false) {
                    continue;
                }

                // Create temp file with correct extension
                $extension = pathinfo($imageData['name'], PATHINFO_EXTENSION);
                $tempFile = tempnam(sys_get_temp_dir(), 'upload') . '.' . $extension;
                file_put_contents($tempFile, $imageContent);

                // Create and configure the image entity
                $image = new Image();
                $uploadedFile = new UploadedFile(
                    $tempFile,
                    $imageData['name'],
                    $imageData['mimeType'],
                    null,
                    true
                );

                $image->setFile($uploadedFile);
                $image->setCreatedAt(new \DateTimeImmutable());
                $image->setUpdatedAt(new \DateTimeImmutable());
                $image->setOffer($offer);
                // dd($image);
                $em->persist($image);
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

        return $this->json($offer, Response::HTTP_CREATED, [], [
            'groups' => ['offer:read', 'image:read'],
        ]);
    }

    #[Route('/products/{id}', name: 'api_get_product', methods: ['GET'])]
    #[OA\Tag(name: 'Offres')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getProduct(Offer $offer): JsonResponse
    {
        return $this->json($offer, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
        ]);
    }


}