<?php
namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository
    )
    {
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $resourceOwnerName = $response->getResourceOwner()->getName();
        $resourceOwnerId = $response->getUserIdentifier();
        $email = $response->getEmail();

        $property = $resourceOwnerName . 'Id';
        $user = $this->userRepository->findOneBy([$property => $resourceOwnerId]);

        if (!$user && $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
        }

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setIsVerified(true);
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
