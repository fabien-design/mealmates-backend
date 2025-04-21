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
            'region' => 'Île-de-France',
            'latitude' => 48.8698,
            'longitude' => 2.3075
        ],
        [
            'identifier' => 'admin_2',
            'city' => 'Versailles',
            'zipCode' => '78000',
            'address' => '7 rue de la Paroisse',
            'region' => 'Île-de-France',
            'latitude' => 48.8025,
            'longitude' => 2.1339
        ],
        [
            'identifier' => 'alicia_1',
            'city' => 'Lyon',
            'zipCode' => '69002',
            'address' => '15 place Bellecour',
            'region' => 'Auvergne-Rhône-Alpes',
            'latitude' => 45.7578,
            'longitude' => 4.8320
        ],
        [
            'identifier' => 'thomas_1',
            'city' => 'Bordeaux',
            'zipCode' => '33000',
            'address' => '45 cours de l\'Intendance',
            'region' => 'Nouvelle-Aquitaine',
            'latitude' => 44.8428,
            'longitude' => -0.5731
        ],
        [
            'identifier' => 'thomas_2',
            'city' => 'Arcachon',
            'zipCode' => '33120',
            'address' => '12 boulevard de la Plage',
            'region' => 'Nouvelle-Aquitaine',
            'latitude' => 44.6595,
            'longitude' => -1.1643
        ],
        [
            'identifier' => 'lucas_1',
            'city' => 'Marseille',
            'zipCode' => '13001',
            'address' => '76 La Canebière',
            'region' => 'Provence-Alpes-Côte d\'Azur',
            'latitude' => 43.2969,
            'longitude' => 5.3744
        ],
        [
            'identifier' => 'celine_1',
            'city' => 'Strasbourg',
            'zipCode' => '67000',
            'address' => '8 place Kléber',
            'region' => 'Grand Est',
            'latitude' => 48.5839,
            'longitude' => 7.7455
        ],
        [
            'identifier' => 'celine_2',
            'city' => 'Colmar',
            'zipCode' => '68000',
            'address' => '24 rue des Marchands',
            'region' => 'Grand Est',
            'latitude' => 48.0765,
            'longitude' => 7.3577
        ],
        [
            'identifier' => 'maxime_1',
            'city' => 'Nantes',
            'zipCode' => '44000',
            'address' => '18 rue Crébillon',
            'region' => 'Pays de la Loire',
            'latitude' => 47.2136,
            'longitude' => -1.5594
        ],
        [
            'identifier' => 'nicolas_1',
            'city' => 'Rennes',
            'zipCode' => '35000',
            'address' => '9 rue de Nemours',
            'region' => 'Bretagne',
            'latitude' => 48.1118,
            'longitude' => -1.6794
        ],
        [
            'identifier' => 'emilie_1',
            'city' => 'Grenoble',
            'zipCode' => '38000',
            'address' => '27 boulevard Gambetta',
            'region' => 'Auvergne-Rhône-Alpes',
            'latitude' => 45.1885,
            'longitude' => 5.7245
        ],
        [
            'identifier' => 'emilie_2',
            'city' => 'Annecy',
            'zipCode' => '74000',
            'address' => '14 rue Royale',
            'region' => 'Auvergne-Rhône-Alpes',
            'latitude' => 45.8992,
            'longitude' => 6.1294
        ],
        [
            'identifier' => 'david_1',
            'city' => 'Nice',
            'zipCode' => '06000',
            'address' => '41 avenue Jean Médecin',
            'region' => 'Provence-Alpes-Côte d\'Azur',
            'latitude' => 43.7031,
            'longitude' => 7.2661
        ],
        [
            'identifier' => 'sophie_1',
            'city' => 'Senlis',
            'zipCode' => '60300',
            'address' => '10 Place Henri IV',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2077,
            'longitude' => 2.5857
        ],
        [
            'identifier' => 'julie_1',
            'city' => 'Senlis',
            'zipCode' => '60300',
            'address' => '15 Rue du Châtel',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2068,
            'longitude' => 2.5834
        ],
        [
            'identifier' => 'admin_3',
            'city' => 'Senlis',
            'zipCode' => '60300',
            'address' => '3 Place Notre-Dame',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2073,
            'longitude' => 2.5849
        ],
        [
            'identifier' => 'sophie_2',
            'city' => 'Senlis',
            'zipCode' => '60300',
            'address' => '22 Rue de la République',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2075,
            'longitude' => 2.5852
        ],
        [
            'identifier' => 'lucas_2',
            'city' => 'Senlis',
            'zipCode' => '60300',
            'address' => '8 Rue Bellon',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2079,
            'longitude' => 2.5848
        ],
        [
            'identifier' => 'celine_3',
            'city' => 'Senlis',
            'zipCode' => '60300',
            'address' => '41 Rue Vieille de Paris',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2090,
            'longitude' => 2.5821
        ],
        [
            'identifier' => 'maxime_2',
            'city' => 'Chantilly',
            'zipCode' => '60500',
            'address' => '7 Rue du Connétable',
            'region' => 'Hauts-de-France',
            'latitude' => 49.1943,
            'longitude' => 2.4818
        ],
        [
            'identifier' => 'julie_2',
            'city' => 'Chantilly',
            'zipCode' => '60500',
            'address' => '4 Avenue du Maréchal Joffre',
            'region' => 'Hauts-de-France',
            'latitude' => 49.1935,
            'longitude' => 2.4767
        ],
        [
            'identifier' => 'nicolas_2',
            'city' => 'Compiègne',
            'zipCode' => '60200',
            'address' => '12 Place de l\'Hôtel de Ville',
            'region' => 'Hauts-de-France',
            'latitude' => 49.4177,
            'longitude' => 2.8273
        ],
        [
            'identifier' => 'emilie_3',
            'city' => 'Compiègne',
            'zipCode' => '60200',
            'address' => '5 Rue Saint-Corneille',
            'region' => 'Hauts-de-France',
            'latitude' => 49.4172,
            'longitude' => 2.8263
        ],
        [
            'identifier' => 'david_2',
            'city' => 'Creil',
            'zipCode' => '60100',
            'address' => '15 Rue de la République',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2604,
            'longitude' => 2.4719
        ],
        [
            'identifier' => 'admin_4',
            'city' => 'Creil',
            'zipCode' => '60100',
            'address' => '8 Place Carnot',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2598,
            'longitude' => 2.4734
        ],
        [
            'identifier' => 'alicia_2',
            'city' => 'Crépy-en-Valois',
            'zipCode' => '60800',
            'address' => '9 Place Jean-Philippe Rameau',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2345,
            'longitude' => 2.8878
        ],
        [
            'identifier' => 'thomas_3',
            'city' => 'Crépy-en-Valois',
            'zipCode' => '60800',
            'address' => '3 Rue Nationale',
            'region' => 'Hauts-de-France',
            'latitude' => 49.2350,
            'longitude' => 2.8896
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
            $addressEntity->setLatitude($address['latitude']);
            $addressEntity->setLongitude($address['longitude']);
            $manager->persist($addressEntity);
            $this->addReference(self::REFERENCE_IDENTIFIER . $address['identifier'], $addressEntity);
        }

        $manager->flush();
    }
}
