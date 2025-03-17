<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\EmailValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/api/v1')]
class UserController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private ParameterBagInterface $params
    )
    {
    }

    #[Route('/register', name: 'app_user_register', methods: ['POST'])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\RequestBody(
        description: 'User registration data',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'password123'),
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'sexe', type: 'boolean', example: true, description: 'true for male, false for female')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'User registered successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'User registered successfully. Please check your email to verify your account.'),
                new OA\Property(property: 'userId', type: 'integer', example: 1)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid input',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 400),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid input data')
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'Email already exists',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 409),
                new OA\Property(property: 'message', type: 'string', example: 'Email already exists')
            ]
        )
    )]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password']) || !isset($data['firstName']) || !isset($data['lastName'])) {
            return $this->json([
                'status' => 400,
                'message' => 'Missing required fields: email, password, firstName, lastName'
            ], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'status' => 409,
                'message' => 'Email already exists'
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);

        if (isset($data['sexe'])) {
            $user->setSexe($data['sexe']);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $violations = $validator->validate($user);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            
            return $this->json([
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $sender = $this->params->get('app.email_sender');
        $senderName = $this->params->get('app.email_sender_name');

        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            (new TemplatedEmail())
                ->from(new Address($sender, $senderName))
                ->to($user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate('emails/verification.html.twig')
                ->context([
                    'user' => $user
                ])
        );

        $jwt = $jwtManager->create($user);
        
        return $this->json([
            'token' => $jwt,
        ], Response::HTTP_CREATED);
    }
    
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\Parameter(
        name: 'id',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'token',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'expires',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'signature',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Email verified successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Email verified successfully')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 400),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired token')
            ]
        )
    )]
    public function verifyEmail(Request $request, UserRepository $userRepository): JsonResponse
    {
        $id = $request->query->get('id');
        
        if (null === $id) {
            return $this->json([
                'status' => 400,
                'message' => 'Missing id parameter'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $user = $userRepository->find($id);
        
        if (null === $user) {
            return $this->json([
                'status' => 404,
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            return $this->json([
                'status' => 400,
                'message' => $exception->getReason(),
                'code' => 'VERIFICATION_FAILED'
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Email verified successfully'
        ]);
    }
    
    #[Route('/resend-verification-email', name: 'app_resend_verification', methods: ['POST'])]
    #[OA\Tag(name: 'Authentication')]
    #[OA\RequestBody(
        description: 'User email',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'user@example.com')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Verification email sent successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Verification email sent successfully')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'User not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string', example: 'User not found')
            ]
        )
    )]
    public function resendVerificationEmail(
        Request $request, 
        UserRepository $userRepository
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['email'])) {
            return $this->json([
                'status' => 400,
                'message' => 'Email is required'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $user = $userRepository->findOneBy(['email' => $data['email']]);
        
        if (!$user) {
            return $this->json([
                'status' => 404,
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($user->isValid()) {
            return $this->json([
                'message' => 'Email is already verified'
            ]);
        }

        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            (new TemplatedEmail())
                ->from(new Address('no-reply@mealmates.com', 'MealMates'))
                ->to($user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate('emails/verification.html.twig')
                ->context([
                    'user' => $user
                ])
        );
        
        return $this->json([
            'message' => 'Verification email sent successfully'
        ]);
    }
}
