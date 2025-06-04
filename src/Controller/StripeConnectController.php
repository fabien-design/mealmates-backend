<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Annotation\Model;

#[Route('/api/v1/stripe')]
#[OA\Tag(name: 'Stripe')]
class StripeConnectController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/connect/register', name: 'api_stripe_connect_register', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Crée un compte Stripe Connect pour le vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'accountId', type: 'string', example: 'acct_123456789'),
                new OA\Property(property: 'onboardingUrl', type: 'string', example: 'https://connect.stripe.com/setup/e/acct_123456789/...')
            ]
        )
    )]
    public function registerConnectAccount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Si l'utilisateur a déjà un compte Stripe Connect
        if ($user->getStripeConnectId()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous avez déjà un compte Stripe Connect'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            // Créer un nouveau compte Connect pour le vendeur
            $account = $this->stripeService->createConnectAccount($user);
            
            // Sauvegarder l'ID du compte dans l'entité User
            $user->setStripeConnectId($account->id);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            // Générer l'URL d'onboarding pour que le vendeur complète son profil
            $onboardingUrl = $this->stripeService->generateOnboardingLink($account->id);
            
            return $this->json([
                'success' => true,
                'accountId' => $account->id,
                'onboardingUrl' => $onboardingUrl
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte Stripe Connect: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/connect/onboarding', name: 'api_stripe_connect_onboarding', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Génère un lien d\'onboarding pour compléter ou mettre à jour le profil vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'onboardingUrl', type: 'string', example: 'https://connect.stripe.com/setup/e/acct_123456789/...')
            ]
        )
    )]
    public function getOnboardingLink(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $stripeConnectId = $user->getStripeConnectId();
        
        if (!$stripeConnectId) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'avez pas encore de compte Stripe Connect. Veuillez d\'abord en créer un.'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $onboardingUrl = $this->stripeService->generateOnboardingLink($stripeConnectId);
            
            return $this->json([
                'success' => true,
                'onboardingUrl' => $onboardingUrl
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du lien d\'onboarding: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/connect/account', name: 'api_stripe_connect_account', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Récupère les informations du compte Stripe Connect du vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'hasAccount', type: 'boolean', example: true),
                new OA\Property(property: 'isReady', type: 'boolean', example: true),
                new OA\Property(property: 'accountId', type: 'string', example: 'acct_123456789'),
                new OA\Property(property: 'details', type: 'object', properties: [
                    new OA\Property(property: 'charges_enabled', type: 'boolean', example: true),
                    new OA\Property(property: 'payouts_enabled', type: 'boolean', example: true),
                    new OA\Property(property: 'requirements', type: 'object')
                ])
            ]
        )
    )]
    public function getAccountInfo(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $stripeConnectId = $user->getStripeConnectId();
        
        $hasAccount = $stripeConnectId !== null;
        $isReady = false;
        $details = null;
        
        if ($hasAccount) {
            try {
                $account = $this->stripeService->getStripe()->accounts->retrieve($stripeConnectId);
                $isReady = $account->charges_enabled && $account->payouts_enabled;
                $details = [
                    'charges_enabled' => $account->charges_enabled,
                    'payouts_enabled' => $account->payouts_enabled,
                    'requirements' => $account->requirements
                ];
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur lors de la récupération des informations du compte: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        return $this->json([
            'hasAccount' => $hasAccount,
            'isReady' => $isReady,
            'accountId' => $stripeConnectId,
            'details' => $details
        ]);
    }

    #[Route('/connect/dashboard', name: 'api_stripe_connect_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Génère un lien vers le dashboard Stripe Express du vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'dashboardUrl', type: 'string', example: 'https://express.stripe.com/...')
            ]
        )
    )]
    public function getDashboardLink(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $stripeConnectId = $user->getStripeConnectId();
        
        if (!$stripeConnectId) {
            return $this->json([
                'success' => false,
                'message' => 'Vous n\'avez pas de compte Stripe Connect'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $dashboard = $this->stripeService->generateDashboardLink($stripeConnectId);
            
            return $this->json([
                'success' => true,
                'dashboardUrl' => $dashboard
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du lien vers le dashboard: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}