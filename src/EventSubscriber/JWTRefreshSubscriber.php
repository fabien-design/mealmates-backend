<?php

namespace App\EventSubscriber;

use App\Controller\OAuthController;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class JWTRefreshSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private OAuthController $oauthController;
    private TokenStorageInterface $tokenStorage;
    private LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        OAuthController $oauthController,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ) {
        $this->requestStack = $requestStack;
        $this->oauthController = $oauthController;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RefreshEvent::class => 'onTokenRefreshed',
        ];
    }

    public function onTokenRefreshed(RefreshEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $oauthCookie = $request->cookies->get('oauth_data');
        if (!$oauthCookie) {
            return;
        }

        try {
            $oauthData = json_decode(base64_decode($oauthCookie), true);
            if (!$oauthData || !isset($oauthData['provider']) || !isset($oauthData['refresh_token'])) {
                return;
            }

            $refreshRequest = $request->duplicate();
            $refreshRequest->request->add([
                'oauth' => [
                    'provider' => $oauthData['provider'],
                    'refresh_token' => $oauthData['refresh_token'],
                ]
            ]);

            $response = $this->oauthController->refreshToken($refreshRequest);

            if ($response->getStatusCode() === 200) {
                $responseContent = json_decode($response->getContent(), true);
                if (isset($responseContent['oauth_data'])) {
                    
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing OAuth token: ' . $e->getMessage());
        }
    }
}
