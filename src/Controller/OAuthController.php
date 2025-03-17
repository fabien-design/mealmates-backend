<?php
// src/Controller/OAuthController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMap;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Attributes as OA;

class OAuthController extends AbstractController
{

    public function __construct(
        private ParameterBagInterface $params,
        private ResourceOwnerMap $resourceOwnerMap
    ) {}

    #[Route('/login/success', name: 'login_success')]
    public function connectSuccess(): Response
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
        dd($oauthData);
        $encodedData = base64_encode(json_encode($oauthData));

        $frontendUrl = $this->params->get('app.frontend_url');
        
        return $this->redirect($frontendUrl . '/auth-callback?oauth_data=' . $encodedData);
    }

    #[Route('/api/v1/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/token/refresh', description: 'Rafraîchit un access_token OAuth expiré')]
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
                new OA\Property(property: 'access_token', type: 'string', description: 'Nouveau token d\'accès'),
                new OA\Property(property: 'refresh_token', type: 'string', description: 'Nouveau refresh token (ou l\'ancien si non fourni par le provider)'),
                new OA\Property(property: 'expires_in', type: 'integer', description: 'Durée de validité du token en secondes'),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', description: 'Date d\'expiration du token'),
                new OA\Property(property: 'provider', type: 'string', description: 'Le fournisseur OAuth utilisé')
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
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? null;
        $provider = $data['provider'] ?? null;
        
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

        $encodedData = base64_encode(json_encode($oauthData));

        return $this->json(['oauth_data' => $encodedData]);
    }
}
