<?php
// src/Controller/OAuthController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OAuthController extends AbstractController
{

    public function __construct(private ParameterBagInterface $params) {}

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

        $encodedData = base64_encode(json_encode($oauthData));

        $frontendUrl = $this->params->get('app.frontend_url');
        
        return $this->redirect($frontendUrl . '/auth-callback?oauth_data=' . $encodedData);
    }
}