<?php

namespace App\Serializer\Normalizer;

use App\Entity\Offer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OfferNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (
            isset($data['address']) &&
            isset($data['address']['latitude']) &&
            isset($data['address']['longitude'])
        ) {

            $exactLat = $data['address']['latitude'];
            $exactLng = $data['address']['longitude'];

            $approxLat = $this->approximateCoordinate($exactLat);
            $approxLng = $this->approximateCoordinate($exactLng);

            $data['position'] = [$approxLat, $approxLng];

            // suppr l'addresse du vendeur pour la confidentialité (Hugo l'a demandé)
            unset($data['address']);
        }

        if (isset($data['seller']) && isset($data['seller']['last_name'])) {
            $data['seller']['last_name'] = substr($data['seller']['last_name'], 0, 1) . '.';
        }

        if (property_exists($object, 'distance')) {
            $data['distance'] = $object->distance;
        }

        return $data;
    }

    // fonction de claude.ai
    private function approximateCoordinate(float $coordinate): float
    {
        // Ajouter un léger décalage aléatoire (jusqu'à ~100m)
        $randomOffset = (mt_rand(-100, 100) / 100000); // ±0.001 degré, approximativement ±100m
        return $coordinate + $randomOffset;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Offer;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Offer::class => true,
        ];
    }
}