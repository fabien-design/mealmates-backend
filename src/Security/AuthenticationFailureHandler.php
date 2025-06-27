<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler as LexikAuthenticationFailureHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class AuthenticationFailureHandler extends LexikAuthenticationFailureHandler
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JWTAuthenticationFailureResponse
    {
        $response = parent::onAuthenticationFailure($request, $exception);

        $data = [
            'success' => false,
            'message' => $exception->getMessage()
        ];

        if ($exception instanceof CustomUserMessageAccountStatusException) {
            $response->setStatusCode(401);
        }

        $response->setData($data);

        return $response;
    }
}
