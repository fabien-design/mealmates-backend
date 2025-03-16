<?php
namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $resourceOwnerName = $response->getResourceOwner()->getName();
        $resourceOwnerId = $response->getUserIdentifier();
        $email = $response->getEmail();

        $property = $resourceOwnerName . 'Id';
        $user = $this->em->getRepository(User::class)->findOneBy([$property => $resourceOwnerId]);

        if (!$user && $email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        }

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setIsEmailValid(true);
            $user->setPassword('');
            $user->setRoles(['ROLE_USER']);
            
            $realName = $response->getRealName() ?: '';
            $nameParts = explode(' ', $realName);
            if (count($nameParts) > 1) {
                $user->setFirstName($nameParts[0]);
                $user->setLastName(implode(' ', array_slice($nameParts, 1)));
            } else {
                $user->setFirstName($realName);
                $user->setLastName('');
            }
        }
 
        $setter = 'set' . ucfirst($resourceOwnerName) . 'Id';
        $user->$setter($resourceOwnerId);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
