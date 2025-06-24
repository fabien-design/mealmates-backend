<?php

namespace App\Serializer\Normalizer;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Offer;
use App\Entity\Review;
use App\Entity\Transaction;
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
        return $data instanceof Offer || $data instanceof Conversation || $data instanceof Message || $data instanceof Transaction || $data instanceof Review;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Offer::class => true,
            Conversation::class => true,
            Message::class => true,
            Transaction::class => true,
            Review::class => true,
        ];
    }
}
