<?php

namespace App\Serializer\Denormalizer;

use App\Entity\SavedSearchFilters;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SavedSearchFiltersDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'SAVED_SEARCH_FILTERS_DENORMALIZER_ALREADY_CALLED';

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return SavedSearchFilters::class === $type
            && !array_key_exists(self::ALREADY_CALLED, $context)
            && is_array($data);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        if (isset($context['object_to_populate'])) {
            $savedSearch = $context['object_to_populate'];
        } else {
            $savedSearch = new SavedSearchFilters();
        }

        $this->applyTransformedData($data, $savedSearch);
        
        return $savedSearch;
    }
    
    private function applyTransformedData(array $data, SavedSearchFilters $savedSearch): void
    {
        if (isset($data['latitude'])) {
            $savedSearch->setLatitude($data['latitude']);
        }
        
        if (isset($data['longitude'])) {
            $savedSearch->setLongitude($data['longitude']);
        }

        if (isset($data['filters'])) {
            $filters = $data['filters'];

            if (isset($filters['productTypes'])) {
                $savedSearch->setProductTypes($filters['productTypes']);
            }
            
            if (isset($filters['dietaryPreferences'])) {
                $savedSearch->setDietaryPreferences($filters['dietaryPreferences']);
            }
            
            if (isset($filters['expirationDate'])) {
                $savedSearch->setExpirationDate($filters['expirationDate']);
            }
            
            if (isset($filters['distance'])) {
                $savedSearch->setRadius($filters['distance']);
            }
            
            if (isset($filters['price'])) {
                if (isset($filters['price']['min'])) {
                    $savedSearch->setMinPrice($filters['price']['min']);
                }
                
                if (isset($filters['price']['max'])) {
                    $savedSearch->setMaxPrice($filters['price']['max']);
                }
            }
            
            // if (isset($filters['minSellerRating'])) {
            //     $savedSearch->setMinSellerRating($filters['minSellerRating']);
            // }
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            SavedSearchFilters::class => true,
        ];
    }
}
