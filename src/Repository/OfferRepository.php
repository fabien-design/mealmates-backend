<?php

namespace App\Repository;

use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

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
            ->where('o.soldAt IS NULL');

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
            $qb->join('o.food_preferences', 'fp')
                ->andWhere('fp.name IN (:dietaryPreferences)')
                ->setParameter('dietaryPreferences', $filters['dietaryPreferences']);
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
            ->orderBy('o.expiryDate', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
