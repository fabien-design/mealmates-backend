<?php

namespace App\DataFixtures;

use App\Entity\Allergen;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserAllergenFixtures extends Fixture implements DependentFixtureInterface
{

    public const REFERENCE_IDENTIFIER = 'user_allergen_';
    public const USER_IDENTIFIER = 'userRef_';
    public const ALLERGEN_IDENTIFIER = 'allergen_';

    public const USER_ALLERGEN = [
        [
            'user' => 'thomas',
            'allergen' => [
                'Crustacés',
                'Œufs',
                'Poisson',
            ]
        ],
        [
            'user' => 'sophie',
            'allergen' => [
                'Fruits à coque'
            ]
            ],
        [
            'user' => 'maxime',
            'allergen' => [
                'Lait',
                'Mollusques'
            ]
        ],
        [
            'user' => 'julie',
            'allergen' => [
                'Gluten',
                'Crustacés',
                'Œufs',
                'Poisson',
                'Arachides',
                'Soja',
                'Lait',
                'Fruits à coque',
                'Céleri',
                'Moutarde',
                'Graines de sésame',
                'Anhydride sulfureux et sulfites',
                'Lupin',
                'Mollusques'
            ]
        ],
        [
            'user' => 'david',
            'allergen' => ['Lait']
        ]
    ];


    public function load(ObjectManager $manager): void
    {
        foreach (self::USER_ALLERGEN as $userAllergen) {
            $user = $this->getReference(self::USER_IDENTIFIER . $userAllergen['user'], User::class);
            foreach ($userAllergen['allergen'] as $allergen) {
                $user->addAllergen($this->getReference(self::ALLERGEN_IDENTIFIER . $allergen, Allergen::class));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            AllergenFixtures::class,
        ];
    }
}
