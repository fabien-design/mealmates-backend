<?php

namespace App\DataFixtures;

use App\Entity\Allergen;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AllergenFixtures extends Fixture
{

    public const REFERENCE_IDENTIFIER = 'allergen_';

    public const ALLERGENS = [
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
    ]; 

    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < count(self::ALLERGENS); $i++) {
            $allergen = new Allergen();
            $allergen->setName(self::ALLERGENS[$i]);
            $manager->persist($allergen);
            $this->addReference(self::REFERENCE_IDENTIFIER . self::ALLERGENS[$i], $allergen);
        }

        $manager->flush();
    }
}
