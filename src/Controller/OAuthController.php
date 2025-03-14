<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OAuthController extends AbstractController
{
    // #[Route('/connect', name: 'connect')]
    // public function connect(): Response
    // {
    //     return $this->render('oauth/connect.html.twig');
    // }

    #[Route('/connect/success', name: 'connect_success')]
    public function connectSuccess(): Response
    {
        $this->addFlash('success', 'Connexion réussie via authentification externe.');
        dd('connect_success');
        return $this->redirectToRoute('app_homepage');
    }
}
