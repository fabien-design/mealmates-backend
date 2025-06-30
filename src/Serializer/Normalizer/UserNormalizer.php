<?php

namespace App\Serializer\Normalizer;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UserNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($context['skip_anonymization']) && $context['skip_anonymization'] === true) {
            return $data;
        }

        if (isset($data['last_name'])) {
            $data['last_name'] = $this->stringToOneLetter($data['last_name']);
        }

        if (isset($data['seller']) && isset($data['seller']['last_name'])) {
            $data['seller'] = $this->anonymizeLastName($data['seller']);
        }

        if (isset($data['buyer']) && isset($data['buyer']['last_name'])) {
            $data['buyer'] = $this->anonymizeLastName($data['buyer']);
        }

        if (isset($data['lastMessage']) && is_array($data['lastMessage'])) {
            if (isset($data['lastMessage']['sender']) && isset($data['lastMessage']['sender']['last_name'])) {
                $data['lastMessage']['sender'] = $this->anonymizeLastName($data['lastMessage']['sender']);
            }
        }

        if (isset($data['sender']) && isset($data['sender']['last_name'])) {
            $data['sender'] = $this->anonymizeLastName($data['sender']);
        }

        return $data;
    }

    private function anonymizeLastName(array $user): array
    {
        $user['last_name'] = $this->stringToOneLetter($user['last_name']);

        return $user;
    }

    public function stringToOneLetter(string $name): string
    {
        return $name[0] . '.';
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }
}
