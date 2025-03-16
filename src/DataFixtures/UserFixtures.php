<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const REFERENCE_IDENTIFIER = 'userRef_';

    public const USERS = [
        [
            'identifier' => 'admin',
            'email'=> 'admin@example.com',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'password' => 'admin',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'isEmailValid' => true,
            'sexe' => true // H
        ],
        [
            'identifier' => 'alicia',
            'email'=> 'alicia@test.com',
            'roles' => ['ROLE_USER'],
            'password' => 'alicia',
            'first_name' => 'Alicia',
            'last_name' => 'Garcia',
            'isEmailValid' => true,
            'sexe' => false // F
        ],
        [
            'identifier' => 'thomas',
            'email'=> 'thomas.dubois@gmail.com',
            'roles' => ['ROLE_USER'],
            'password' => 'thomas123',
            'first_name' => 'Thomas',
            'last_name' => 'Dubois',
            'isEmailValid' => true,
            'sexe' => true
        ],
        [
            'identifier' => 'sophie',
            'email'=> 'sophie.martin@outlook.fr',
            'roles' => ['ROLE_USER'],
            'password' => 'sophie2025',
            'first_name' => 'Sophie',
            'last_name' => 'Martin',
            'isEmailValid' => true,
            'sexe' => false
        ],
        [
            'identifier' => 'lucas',
            'email'=> 'lucas.bernard@yahoo.com',
            'roles' => ['ROLE_USER'],
            'password' => 'lucasb',
            'first_name' => 'Lucas',
            'last_name' => 'Bernard',
            'isEmailValid' => false,
            'sexe' => true
        ],
        [
            'identifier' => 'celine',
            'email'=> 'celine.petit@hotmail.com',
            'roles' => ['ROLE_USER'],
            'password' => 'celinep',
            'first_name' => 'Céline',
            'last_name' => 'Petit',
            'isEmailValid' => true,
            'sexe' => false
        ],
        [
            'identifier' => 'maxime',
            'email'=> 'maxime.leroy@gmail.com',
            'roles' => ['ROLE_USER'],
            'password' => 'max1234',
            'first_name' => 'Maxime',
            'last_name' => 'Leroy',
            'isEmailValid' => true,
            'sexe' => true
        ],
        [
            'identifier' => 'julie',
            'email'=> 'julie.moreau@free.fr',
            'roles' => ['ROLE_USER'],
            'password' => 'julie2024',
            'first_name' => 'Julie',
            'last_name' => 'Moreau',
            'isEmailValid' => false,
            'sexe' => false
        ],
        [
            'identifier' => 'nicolas',
            'email'=> 'nicolas.durand@orange.fr',
            'roles' => ['ROLE_USER'],
            'password' => 'nico75',
            'first_name' => 'Nicolas',
            'last_name' => 'Durand',
            'isEmailValid' => true,
            'sexe' => true
        ],
        [
            'identifier' => 'emilie',
            'email'=> 'emilie.lambert@laposte.net',
            'roles' => ['ROLE_USER'],
            'password' => 'emilam',
            'first_name' => 'Emilie',
            'last_name' => 'Lambert',
            'isEmailValid' => true,
            'sexe' => false
        ],
        [
            'identifier' => 'david',
            'email'=> 'david.roux@sfr.fr',
            'roles' => ['ROLE_USER'],
            'password' => 'davidr',
            'first_name' => 'David',
            'last_name' => 'Roux',
            'isEmailValid' => true,
            'sexe' => true
        ],
    ];
        

    private $passwordEncoder;

    public function __construct(UserPasswordHasherInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager): void
    {
        for($i = 0; $i < count(self::USERS); $i++) {
            $user = new User();
            $user->setEmail(self::USERS[$i]['email']);
            $user->setRoles(self::USERS[$i]['roles']);
            $user->setPassword($this->passwordEncoder->hashPassword($user, self::USERS[$i]['password']));
            $user->setFirstName(self::USERS[$i]['first_name']);
            $user->setLastName(self::USERS[$i]['last_name']);
            $user->setIsVerified(self::USERS[$i]['isEmailValid']);
            $user->setSexe(self::USERS[$i]['sexe']);
            $this->addReference(self::REFERENCE_IDENTIFIER . self::USERS[$i]['identifier'], $user);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
