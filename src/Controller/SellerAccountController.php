<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[Route('/api/v1/seller')]
#[OA\Tag(name: 'Compte Vendeur')]
class SellerAccountController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private readonly TransactionRepository $transactionRepository
    ) {
    }

    #[Route('/banking/setup', name: 'api_seller_banking_setup', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Crée un lien pour configurer les informations bancaires du vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'onboardingUrl', type: 'string', example: 'https://connect.stripe.com/setup/...')
            ]
        )
    )]
    public function setupBanking(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        try {
            $onboardingUrl = $this->stripeService->createSellerAccount($user);
            
            return $this->json([
                'success' => true,
                'onboardingUrl' => $onboardingUrl,
                'message' => 'Veuillez compléter vos informations bancaires pour recevoir les paiements'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte vendeur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/banking/status', name: 'api_seller_banking_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Statut du compte bancaire du vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'hasAccount', type: 'boolean', example: true),
                new OA\Property(property: 'isReady', type: 'boolean', example: true),
                new OA\Property(property: 'canReceivePayments', type: 'boolean', example: true),
                new OA\Property(property: 'setupUrl', type: 'string', nullable: true)
            ]
        )
    )]
    public function getBankingStatus(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $stripeAccountId = $user->getStripeAccountId();
        
        $hasAccount = $stripeAccountId !== null;
        $isReady = false;
        $canReceivePayments = false;
        $setupUrl = null;
        
        if ($hasAccount) {
            try {
                $account = $this->stripeService->getStripe()->accounts->retrieve($stripeAccountId);
                $isReady = $account->details_submitted;
                $canReceivePayments = $account->payouts_enabled;

                if (!$isReady) {
                    $setupUrl = $this->stripeService->createSellerAccount($user);
                }
            } catch (\Exception $e) {
                $hasAccount = false;
                $user->setStripeAccountId(null);
                $this->entityManager->flush();
            }
        }
        
        return $this->json([
            'hasAccount' => $hasAccount,
            'isReady' => $isReady,
            'canReceivePayments' => $canReceivePayments,
            'setupUrl' => $setupUrl,
            'message' => $this->getBankingStatusMessage($hasAccount, $isReady, $canReceivePayments)
        ]);
    }

    #[Route('/earnings', name: 'api_seller_earnings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 200,
        description: 'Gains du vendeur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'totalEarnings', type: 'number', format: 'float'),
                new OA\Property(property: 'pendingAmount', type: 'number', format: 'float'),
                new OA\Property(property: 'completedTransactions', type: 'integer'),
                new OA\Property(property: 'pendingTransactions', type: 'integer')
            ]
        )
    )]
    public function getEarnings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $completedTransactions = $this->transactionRepository->findBy([
            'seller' => $user,
            'status' => 'completed'
        ]);
        
        $pendingTransactions = $this->transactionRepository->findBy([
            'seller' => $user,
            'status' => 'pending'
        ]);
        
        $totalEarnings = array_sum(array_map(fn($t) => $t->getAmount(), $completedTransactions));
        $pendingAmount = array_sum(array_map(fn($t) => $t->getAmount(), $pendingTransactions));
        
        return $this->json([
            'totalEarnings' => $totalEarnings,
            'pendingAmount' => $pendingAmount,
            'completedTransactions' => count($completedTransactions),
            'pendingTransactions' => count($pendingTransactions)
        ]);
    }

    private function getBankingStatusMessage(bool $hasAccount, bool $isReady, bool $canReceivePayments): string
    {
        if (!$hasAccount) {
            return 'Vous devez configurer vos informations bancaires pour vendre sur MealMates';
        }
        
        if (!$isReady) {
            return 'Veuillez compléter vos informations bancaires';
        }
        
        if (!$canReceivePayments) {
            return 'Votre compte est en cours de vérification';
        }
        
        return 'Votre compte est prêt à recevoir des paiements !';
    }

    #[Route('/delete', name: 'api_seller_delete_account', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(
        response: 201,
        description: 'Delete seller account',
    )]
    public function deleteAccount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user->getStripeAccountId()) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun compte Stripe trouvé pour cet utilisateur'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $this->stripeService->deleteSellerAccount($user);
            $user->setStripeAccountId(null);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Compte vendeur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du compte vendeur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
