<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        return new Response('
            <html>
                <body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;">
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;">
                        <img src="/images/MealMatesLogo.webp" alt="MealMates Logo" style="width: 150px; height: auto;">
                        <h1 style="text-align:center;">MealMates Backend</h1>
                    </div>
                    <p>API Backend pour l\'application MealMates</p>
                    <div style="margin: 30px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <a href="/admin" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Interface Admin</a>
                        <a href="/api/v1/doc" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">Documentation API</a>
                    </div>
                </body>
            </html>
        ');
    }
}
