<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Entity\User;
use App\Entity\Offer;
use App\Entity\Transaction;
use App\Enums\OfferReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Repository\OfferRepository;
use App\Repository\ReviewRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private ReviewRepository $reviewRepository,
        private UserRepository $userRepository,
        private OfferRepository $offerRepository,
        private TransactionRepository $transactionRepository,
    ) {
    }
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $pendingReviewsCount = $this->reviewRepository
            ->count(['status' => \App\Enums\ReviewStatus::NEED_VERIFICATION]);

        $usersCount = $this->reviewRepository->count([]);
        $offersCount = $this->reviewRepository->count([]);
        $transactionsCount = $this->reviewRepository->count([]);

        return $this->render('admin/dashboard.html.twig', [
            'pending_reviews_count' => $pendingReviewsCount,
            'pending_users_count' => $this->getPendingUsersCount(),
            'pending_offers_count' => $this->getPendingOffersCount(),
            'users_count' => $usersCount,
            'offers_count' => $offersCount,
            'transactions_count' => $transactionsCount,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('MealMates - Administration')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Modération');
        yield MenuItem::linkToCrud('Reviews', 'fa fa-star', Review::class)
            ->setBadge($this->getNeedVerificationReviewsCount(), 'info');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-star', User::class)
            ->setBadge($this->getPendingUsersCount(), 'info');
        yield MenuItem::linkToCrud('Offres', 'fa fa-utensils', Offer::class)
            ->setBadge($this->getPendingOffersCount(), 'info');

        yield MenuItem::section('Gestion');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Offres', 'fa fa-utensils', Offer::class);

        yield MenuItem::section('');
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-external-link', 'homepage');
    }

    private function getNeedVerificationReviewsCount(): int
    {
        return $this->reviewRepository->count(['status' => ReviewStatus::NEED_VERIFICATION]);
    }

    private function getPendingUsersCount(): int
    {
        return $this->userRepository->count(['status' => UserStatus::NEED_VERIFICATION]);
    }

    private function getPendingOffersCount(): int
    {
        return $this->offerRepository->count(['status' => OfferReportStatus::NEED_VERIFICATION]);
    }
}
