<?php

namespace App\DataFixtures;

use App\Entity\FoodPreference;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FoodPreferenceFixtures extends Fixture
{
    public const REFERENCE_IDENTIFIER = 'foodpref_';

    public const PREFERENCES = [
        'Vegan',
        'Vegetarian',
        'Pescatarian',
        'Flexitarian'
    ];

    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < count(self::PREFERENCES); $i++) {
            $foodPreference = new FoodPreference();
            $foodPreference->setName(self::PREFERENCES[$i]);
            $manager->persist($foodPreference);
            $this->addReference(self::REFERENCE_IDENTIFIER . self::PREFERENCES[$i], $foodPreference);
        }

        $manager->flush();
    }
}
