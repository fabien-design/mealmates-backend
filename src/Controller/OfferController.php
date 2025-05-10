<?php

namespace App\Controller;

use App\Repository\OfferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Offer;

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
    #[OA\Parameter(name: 'minSellerRating', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float'))]
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
        
        if ($request->query->has('minSellerRating')) {
            $filters['minSellerRating'] = (float) $request->query->get('minSellerRating');
        }
        
        if ($request->query->has('dietaryPreferences')) {
            $filters['dietaryPreferences'] = explode(',', $request->query->get('dietaryPreferences'));
        }

        $offers = $offerRepository->findNearbyOffers($lat, $lng, $radius, $filters);
       
        return $this->json($offers, Response::HTTP_OK, [], [
            'groups' => ['offer:read'],
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