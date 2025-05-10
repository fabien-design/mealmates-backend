<?php

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MercureTokenService
{
    public function __construct(
        private string $mercureJwtSecret,
        private JWTTokenManagerInterface $jwtManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Génère un token JWT pour un utilisateur pour s'abonner à ses propres sujets
     */
    public function generateUserSubscriptionToken(User $user): string
    {
        $payload = [
            'mercure' => [
                'subscribe' => [
                    // Les sujets auxquels l'utilisateur peut s'abonner
                    $this->urlGenerator->generate('api_user_messages', [
                        'id' => $user->getId()
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    // Permettre de s'abonner aux conversations auxquelles l'utilisateur appartient
                    // Le * sera remplacé par l'ID de conversation côté client
                    $this->urlGenerator->generate('api_conversation_messages', [
                        'id' => '*'
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                ]
            ]
        ];

        // En cas de besoin, utilise directement le service JWT de Lexik
        // return $this->jwtManager->createFromPayload($user, $payload);

        // Ou crée un token spécifique à Mercure
        return $this->createMercureToken($payload);
    }

    /**
     * Crée un token JWT Mercure avec les claims spécifiés
     */
    private function createMercureToken(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $encodedHeader = base64_encode(json_encode($header));
        $encodedPayload = base64_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$encodedHeader.$encodedPayload",
            $this->mercureJwtSecret,
            true
        );

        $encodedSignature = base64_encode($signature);

        return "$encodedHeader.$encodedPayload.$encodedSignature";
    }
}
