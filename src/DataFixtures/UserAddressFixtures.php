<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserAddressFixtures extends Fixture implements DependentFixtureInterface
{
    public const USER_IDENTIFIER = 'userRef_';
    public const ADDRESS_IDENTIFIER = 'address_';

    public const USER_ADDRESS = [
        [
            'user' => 'admin',
            'address' => ['admin_1', 'admin_2']
        ],
        [
            'user' => 'alicia',
            'address' => ['alicia_1']
        ],
        [
            'user' => 'thomas',
            'address' => ['thomas_1', 'thomas_2']
        ],
        [
            'user' => 'lucas',
            'address' => ['lucas_1']
        ],
        [
            'user' => 'celine',
            'address' => ['celine_1', 'celine_2']
        ],
        [
            'user' => 'maxime',
            'address' => ['maxime_1']
        ],
        [
            'user' => 'nicolas',
            'address' => ['nicolas_1']
        ],
        [
            'user' => 'emilie',
            'address' => ['emilie_1', 'emilie_2']
        ],
        [
            'user' => 'david',
            'address' => ['david_1']
        ]
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::USER_ADDRESS as $data) {
            $user = $this->getReference(self::USER_IDENTIFIER . $data['user'], User::class);
            foreach ($data['address'] as $address) {
                $user->addAddress($this->getReference(self::ADDRESS_IDENTIFIER . $address, Address::class));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            AddressFixtures::class
        ];
    }
}
