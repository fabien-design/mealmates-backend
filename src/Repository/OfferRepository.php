<?php

namespace App\Repository;

use App\Entity\Offer;
use App\Enums\OfferReportStatus;
use App\Enums\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use App\Entity\User;
use App\Enums\OfferStatus;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    public function findNearbyOffers(float $lat, float $lng, float $radius, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->join('o.address', 'a')
            ->join('o.seller', 's')
            ->where('o.expiryDate >= :today')
            ->andWhere('o.soldAt IS NULL')
            ->setParameter('today', new \DateTime('today'))
            ->andWhere('s.status LIKE :status')
            ->setParameter('status', UserStatus::APPROVED->value)
            ->andWhere('o.status NOT LIKE :offerStatus OR o.status IS NULL OR o.status = :offerApproved')
            ->setParameter('offerStatus', OfferReportStatus::NEED_VERIFICATION->value)
            ->setParameter('offerApproved', OfferReportStatus::APPROVED->value);

        $this->applyFilters($qb, $filters);

        $potentialOffers = $qb->getQuery()->getResult();

        $offersWithDistance = [];

        foreach ($potentialOffers as $offer) {
            $address = $offer->getAddress();
            if (!$address) {
                continue;
            }

            $offerLat = $address->getLatitude();
            $offerLng = $address->getLongitude();

            if ($offerLat === null || $offerLng === null) {
                continue;
            }

            $distance = $this->calculateDistance($lat, $lng, $offerLat, $offerLng);

            if ($distance <= $radius) {
                $offer->distance = $distance;
                $offersWithDistance[] = $offer;
            }
        }

        usort($offersWithDistance, function ($a, $b) {
            return $a->distance <=> $b->distance;
        });

        return $offersWithDistance;
    }

    /**
     * add Offer filters 
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['productTypes'])) {
            $qb->andWhere('o.name IN (:productTypes)')
                ->setParameter('productTypes', $filters['productTypes']);
        }

        if (isset($filters['minPrice'])) {
            $qb->andWhere('o.price >= :minPrice')
                ->setParameter('minPrice', $filters['minPrice']);
        }

        if (isset($filters['maxPrice'])) {
            $qb->andWhere('o.price <= :maxPrice')
                ->setParameter('maxPrice', $filters['maxPrice']);
        }

        // if (isset($filters['minSellerRating']) && $filters['minSellerRating'] > 0) {
        //     $qb->andWhere('s.rating >= :minSellerRating')
        //         ->setParameter('minSellerRating', $filters['minSellerRating']);
        // }

        if (!empty($filters['expirationDate'])) {
            $today = new \DateTime('today');

            switch ($filters['expirationDate']) {
                case 'today':
                    $qb->andWhere('o.expiryDate = :today')
                        ->setParameter('today', $today);
                    break;

                case 'tomorrow':
                    $tomorrow = (new \DateTime('today'))->modify('+1 day');
                    $qb->andWhere('o.expiryDate = :tomorrow')
                        ->setParameter('tomorrow', $tomorrow);
                    break;

                case 'week':
                    $endOfWeek = (new \DateTime('today'))->modify('+7 days');
                    $qb->andWhere('o.expiryDate BETWEEN :today AND :endOfWeek')
                        ->setParameter('today', $today)
                        ->setParameter('endOfWeek', $endOfWeek);
                    break;
            }
        }

        if (!empty($filters['dietaryPreferences'])) {
            $qb->leftJoin('o.food_preferences', 'fp')
                ->andWhere('fp.id IN (:dietaryPreferences)')
                ->setParameter('dietaryPreferences', $filters['dietaryPreferences']);
        }

        if (!empty($filters['excludeAllergens'])) {
            $qb->leftJoin('o.allergens', 'al')
                ->andWhere('al.id NOT IN (:excludeAllergens) OR al.id IS NULL')
                ->setParameter('excludeAllergens', $filters['excludeAllergens']);
        }
    }

    /**
     * Calcule la distance entre deux points géographiques en mètres
     * en utilisant la formule de Haversine
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Rayon de la Terre en mètres

        // Conversion en radians
        $latRad1 = deg2rad($lat1);
        $lonRad1 = deg2rad($lon1);
        $latRad2 = deg2rad($lat2);
        $lonRad2 = deg2rad($lon2);

        // Différence de latitude et longitude
        $latDiff = $latRad2 - $latRad1;
        $lonDiff = $lonRad2 - $lonRad1;

        // Formule de Haversine
        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos($latRad1) * cos($latRad2) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function findExpiringToday(): array
    {
        $today = new \DateTime('today');
        return $this->createQueryBuilder('o')
            ->andWhere('o.expiryDate = :today')
            ->andWhere('o.soldAt IS NULL')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.name = :category')
            ->andWhere('o.soldAt IS NULL')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();
    }

    public function findExpiringOffers(\DateTimeInterface $expiryDate): array
    {
        $qb = $this->createQueryBuilder('o')
            ->join('o.seller', 's')
            ->where('o.expiryDate BETWEEN :today AND :expiry_date')
            ->andWhere('o.soldAt IS NULL')
            ->andWhere('o.expiryAlertSent = false')
            ->setParameter('today', new \DateTime('today'))
            ->setParameter('expiry_date', $expiryDate)
            ->andWhere('s.status LIKE :status')
            ->setParameter('status', UserStatus::APPROVED->value)
            ->orderBy('o.expiryDate', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les offres d'un utilisateur selon le statut demandé
     * 
     * @param User $user
     * @param string|OfferStatus $status
     * @return Offer[]
     */
    public function findUserOffersByStatus(User $user, string|OfferStatus $status): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.seller = :seller')
            ->setParameter('seller', $user);

        if ($status instanceof OfferStatus) {
            $statusValue = $status->value;
        } else {
            $statusValue = $status;
        }

        $today = new \DateTime();

        switch ($statusValue) {
            case OfferStatus::ACTIVE->value:
                $qb->andWhere('o.soldAt IS NULL')
                    ->andWhere('o.expiryDate >= :today')
                    ->setParameter('today', $today)
                    ->orderBy('o.expiryDate', 'ASC');
                break;
            case OfferStatus::SOLD->value:
                $qb->andWhere('o.soldAt IS NOT NULL')
                    ->orderBy('o.soldAt', 'DESC');
                break;
            case OfferStatus::EXPIRED->value:
                $qb->andWhere('o.soldAt IS NULL')
                    ->andWhere('o.expiryDate < :today')
                    ->setParameter('today', $today)
                    ->orderBy('o.expiryDate', 'DESC');
                break;
            default:
                $qb->orderBy('o.id', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les offres achetées par un utilisateur
     * 
     * @param User $user
     * @return Offer[]
     */
    public function findUserBoughtOffers(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.buyer = :buyer')
            ->setParameter('buyer', $user)
            ->orderBy('o.soldAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}