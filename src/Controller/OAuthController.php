<?php
// src/Controller/OAuthController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMap;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Attributes as OA;

class OAuthController extends AbstractController
{

    public function __construct(
        private ParameterBagInterface $params,
        private ResourceOwnerMap $resourceOwnerMap,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager
    ) {
    }

    private function generateTokenData($user)
    {

    }

    #[Route('/login/success', name: 'login_success')]
    public function connectSuccess(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $token = $this->container->get('security.token_storage')->getToken();
        $oauthData = [];

        if ($token instanceof OAuthToken) {
            $oauthData = [
                'access_token' => $token->getAccessToken(),
                'provider' => $token->getResourceOwnerName(),
                'expires_at' => $token->getExpiresAt() ? $token->getExpiresAt() : null,
                'refresh_token' => $token->getRefreshToken(),
            ];
        }

        $jwt = $this->jwtManager->create($user);

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime('+1 month'))->getTimestamp()
        );

        $this->refreshTokenManager->save($refreshToken);

        $frontendUrl = $this->params->get('app.frontend_url');
        $response = new RedirectResponse($frontendUrl . '/app/discover');

        $jwtExpiry = new \DateTime('+1 hour');
        $refreshExpiry = new \DateTime('+1 month');

        // Définir les cookies HTTP-only
        $response->headers->setCookie(
            new Cookie(
                'jwt_token',           // Nom du cookie
                $jwt,                  // Valeur (JWT token)
                $jwtExpiry,            // Durée d'expiration
                '/',                   // Chemin
                null,                  // Domaine (null = domaine actuel)
                true,                  // Secure (true = HTTPS uniquement)
                true,                  // HTTP only
                false,                 // Raw
                Cookie::SAMESITE_LAX   // SameSite policy (LAX permet la redirection)
            )
        );

        $response->headers->setCookie(
            new Cookie(
                'refresh_token',
                $refreshToken->getRefreshToken(),
                $refreshExpiry,
                '/',
                null,
                true,
                true,
                false,
                Cookie::SAMESITE_LAX
            )
        );

        if (!empty($oauthData)) {
            $response->headers->setCookie(
                new Cookie(
                    'oauth_data',
                    base64_encode(json_encode($oauthData)),
                    $refreshExpiry,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    Cookie::SAMESITE_LAX
                )
            );
        }

        return $response;
    }

    #[Route('/api/v1/token/refresh/oauth', name: 'api_token_refresh_oauth', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/token/refresh/oauth', description: 'Rafraîchit un access_token OAuth expiré', summary: 'Rafraîchir un access_token OAuth')]
    #[OA\RequestBody(
        description: 'Paramètres pour rafraîchir le token',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'refresh_token', type: 'string', description: 'Le refresh_token obtenu lors de l\'authentification'),
                new OA\Property(property: 'provider', type: 'string', description: 'Le fournisseur OAuth (github, google, etc.)')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Token rafraîchi avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...', description: 'Nouveau token JWT'),
                new OA\Property(property: 'refresh_token', type: 'string', example: 'dzuhj3456FZGh09czuhz...', description: 'Nouveau refresh token'),
                new OA\Property(
                    property: 'oauth',
                    type: 'object',
                    description: 'Données OAuth',
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string', example: 'ghu_VQtFQ...', description: 'Nouveau token d\'accès'),
                        new OA\Property(property: 'provider', type: 'string', example: 'github', description: 'Le fournisseur OAuth utilisé'),
                        new OA\Property(property: 'expires_at', type: 'string', example: '1743599559', description: 'Date d\'expiration du token'),
                        new OA\Property(property: 'refresh_token', type: 'string', example: 'ghr_ndtu...', description: 'Nouveau refresh token (ou l\'ancien si non fourni par le provider)')
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Paramètres manquants',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Refresh token et provider sont requis')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Erreur lors du rafraîchissement du token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid refresh token')
            ]
        )
    )]
    #[OA\Tag(name: 'Authentication')]
    public function refreshToken(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data['oauth']) {
            return $this->json(['error' => 'OAuth data is missing'], 400);
        }
        $refreshToken = $data['oauth']['refresh_token'] ?? null;
        $provider = $data['oauth']['provider'] ?? null;

        if (!$refreshToken || !$provider) {
            return $this->json(['error' => 'Refresh token and privider are needed ;)'], 400);
        }

        try {
            $resourceOwner = $this->resourceOwnerMap->getResourceOwnerByName($provider);
            $data = $resourceOwner->refreshAccessToken($refreshToken);
            $oauthData = [
                'access_token' => $data['access_token'],
                'provider' => $provider,
                'expires_at' => $data['expires_in'] ? time() + $data['expires_in'] : null,
                'refresh_token' => $data['refresh_token'],
            ];

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $jwt = $this->jwtManager->create($user);

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime('+1 month'))->getTimestamp()
        );
        $this->refreshTokenManager->save($refreshToken);

        $responseData = [
            'token' => $jwt,
            'refresh_token' => $refreshToken->getRefreshToken(),
            'oauth' => $oauthData
        ];

        $encodedData = base64_encode(json_encode($responseData));

        return $this->json(['oauth_data' => $encodedData]);
    }
}
