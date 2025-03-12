<?php

namespace App\DataFixtures;

use App\Entity\FoodPreference;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFoodPreferenceFixtures extends Fixture implements DependentFixtureInterface
{
    public const USER_IDENTIFIER = 'userRef_';
    public const FOOD_PREFERENCE_IDENTIFIER = 'foodpref_';

    public const USER_FOOD_PREF = [
        ['user' => 'admin', 'foodPreference' => ['Vegan', 'Vegetarian']],
        ['user' => 'alicia', 'foodPreference' => ['Pescatarian']],
        ['user' => 'thomas', 'foodPreference' => ['Flexitarian']],
        ['user' => 'sophie', 'foodPreference' => ['Vegan']],
        ['user' => 'maxime', 'foodPreference' => ['Flexitarian']],
        ['user' => 'julie', 'foodPreference' => ['Vegan']],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::USER_FOOD_PREF as $data) {
            $user = $this->getReference(self::USER_IDENTIFIER . $data['user'], User::class);
            foreach ($data['foodPreference'] as $foodPreference) {
                $user->addFoodPreference($this->getReference(self::FOOD_PREFERENCE_IDENTIFIER . $foodPreference, FoodPreference::class));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            FoodPreferenceFixtures::class
        ];
    }
}
