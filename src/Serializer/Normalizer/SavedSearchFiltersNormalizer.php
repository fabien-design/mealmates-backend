<?php

namespace App\Serializer\Normalizer;

use App\Entity\SavedSearchFilters;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SavedSearchFiltersNormalizer implements NormalizerInterface
{

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer
    )
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        return [
            'id' => $data['id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'filters' => [
                'productTypes' => $data['productTypes'] ?? [],
                'dietaryPreferences' => $data['dietaryPreferences'] ?? [],
                'expirationDate' => $data['expirationDate'] ?? '',
                'distance' => $data['radius'] ?? 0,
                'price' => [
                    'min' => $data['minPrice'] ?? 0,
                    'max' => $data['maxPrice'] ?? 0,
                ],
                // 'minSellerRating' => $data['minSellerRating'] ?? 0,
            ],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof SavedSearchFilters;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            SavedSearchFilters::class => true,
        ];
    }
}
