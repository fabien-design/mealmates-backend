<?php

namespace App\DataFixtures;

use App\Entity\Allergen;
use App\Entity\FoodPreference;
use App\Entity\Image;
use App\Entity\Offer;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OfferFixtures extends Fixture implements DependentFixtureInterface
{
    public const REFERENCE_IDENTIFIER = 'offer_';

    private const OFFERS = [
        [
            'identifier' => '1',
            'title' => 'Briques de lait',
            'description' => 'Briques de lait végétal qui périme bientôt',
            'quantity' => 6,
            'price' => 2,
            'dynamicPrice' => null,
            'allergens' => ['Lait'],
            'food_preferences' => [],
            'seller' => 'admin',
            'buyer' => 'alicia',
            'isRecurring' => false,
            'hasBeenSold' => true,
        ],
        [
            'identifier' => '2',
            'title' => 'Steak',
            'description' => '4 steaks de boeuf qui périment bientôt',
            'quantity' => 4,
            'price' => 10,
            'dynamicPrice' => 7.99,
            'allergens' => [],
            'food_preferences' => [],
            'seller' => 'thomas',
            'buyer' => null,
            'isRecurring' => false,
            'hasBeenSold' => false,
        ],
        [
            'identifier' => '3',
            'title' => 'Pain de la boulangerie',
            'description' => 'Pain frais qui ne sera pas mangé',
            'quantity' => 1,
            'price' => 1,
            'dynamicPrice' => null,
            'allergens' => ['Gluten'],
            'food_preferences' => [],
            'seller' => 'sophie',
            'buyer' => null,
            'isRecurring' => true,
            'hasBeenSold' => false,
        ]
    ];

    public function load(ObjectManager $manager): void
    {
        $dates = ['+1 day', '+2 days', '+1 week'];

        foreach (self::OFFERS as $data) {
            $offer = new Offer();
            $offer->setName($data['title']);
            $offer->setDescription($data['description']);
            $offer->setPrice($data['price']);
            $offer->setQuantity($data['quantity']);
            $offer->setDynamicPrice($data['dynamicPrice']);
            $offer->setIsRecurring($data['isRecurring']);
            $offer->setHasBeenSold($data['hasBeenSold']);
            $offer->setSeller($this->getReference(UserFixtures::REFERENCE_IDENTIFIER . $data['seller'], User::class));
            if ($data['buyer']) {
                $offer->setBuyer($this->getReference(UserFixtures::REFERENCE_IDENTIFIER . $data['buyer'], User::class));
            }
            foreach ($data['allergens'] as $allergen) {
                $offer->addAllergen($this->getReference(AllergenFixtures::REFERENCE_IDENTIFIER . $allergen, Allergen::class));
            }
            foreach ($data['food_preferences'] as $foodPreference) {
                $offer->addFoodPreference($this->getReference(FoodPreferenceFixtures::REFERENCE_IDENTIFIER . $foodPreference, FoodPreference::class));
            }
            $offer->setExpiryDate(new \DateTimeImmutable($dates[array_rand($dates)]));

            $manager->persist($offer);
            $this->addReference(self::REFERENCE_IDENTIFIER . $data['identifier'], $offer);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            AllergenFixtures::class,
            FoodPreferenceFixtures::class,
        ];
    }
}
