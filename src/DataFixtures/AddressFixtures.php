<?php

namespace App\DataFixtures;

use App\Entity\Address;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AddressFixtures extends Fixture
{
    public const REFERENCE_IDENTIFIER = 'address_';

    public const ADDRESS = [
        [
            'identifier' => 'admin_1',
            'city' => 'Paris',
            'zipCode' => '75008',
            'address' => '23 avenue des Champs-Élysées',
            'region' => 'Île-de-France'
        ],
        [
            'identifier' => 'admin_2',
            'city' => 'Versailles',
            'zipCode' => '78000',
            'address' => '7 rue de la Paroisse',
            'region' => 'Île-de-France'
        ],
        [
            'identifier' => 'alicia_1',
            'city' => 'Lyon',
            'zipCode' => '69002',
            'address' => '15 place Bellecour',
            'region' => 'Auvergne-Rhône-Alpes'
        ],
        [
            'identifier' => 'thomas_1',
            'city' => 'Bordeaux',
            'zipCode' => '33000',
            'address' => '45 cours de l\'Intendance',
            'region' => 'Nouvelle-Aquitaine'
        ],
        [
            'identifier' => 'thomas_2',
            'city' => 'Arcachon',
            'zipCode' => '33120',
            'address' => '12 boulevard de la Plage',
            'region' => 'Nouvelle-Aquitaine'
        ],
        [
            'identifier' => 'lucas_1',
            'city' => 'Marseille',
            'zipCode' => '13001',
            'address' => '76 La Canebière',
            'region' => 'Provence-Alpes-Côte d\'Azur'
        ],
        [
            'identifier' => 'celine_1',
            'city' => 'Strasbourg',
            'zipCode' => '67000',
            'address' => '8 place Kléber',
            'region' => 'Grand Est'
        ],
        [
            'identifier' => 'celine_2',
            'city' => 'Colmar',
            'zipCode' => '68000',
            'address' => '24 rue des Marchands',
            'region' => 'Grand Est'
        ],
        [
            'identifier' => 'maxime_1',
            'city' => 'Nantes',
            'zipCode' => '44000',
            'address' => '18 rue Crébillon',
            'region' => 'Pays de la Loire'
        ],
        [
            'identifier' => 'nicolas_1',
            'city' => 'Rennes',
            'zipCode' => '35000',
            'address' => '9 rue de Nemours',
            'region' => 'Bretagne'
        ],
        [
            'identifier' => 'emilie_1',
            'city' => 'Grenoble',
            'zipCode' => '38000',
            'address' => '27 boulevard Gambetta',
            'region' => 'Auvergne-Rhône-Alpes'
        ],
        [
            'identifier' => 'emilie_2',
            'city' => 'Annecy',
            'zipCode' => '74000',
            'address' => '14 rue Royale',
            'region' => 'Auvergne-Rhône-Alpes'
        ],
        [
            'identifier' => 'david_1',
            'city' => 'Nice',
            'zipCode' => '06000',
            'address' => '41 avenue Jean Médecin',
            'region' => 'Provence-Alpes-Côte d\'Azur'
        ]
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ADDRESS as $address) {
            $addressEntity = new Address();
            $addressEntity->setCity($address['city']);
            $addressEntity->setZipCode($address['zipCode']);
            $addressEntity->setAddress($address['address']);
            $addressEntity->setRegion($address['region']);
            $manager->persist($addressEntity);
            $this->addReference(self::REFERENCE_IDENTIFIER . $address['identifier'], $addressEntity);
        }

        $manager->flush();
    }
}
