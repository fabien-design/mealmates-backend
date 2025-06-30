<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\ReviewStatus;
use App\Repository\ReviewRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\Notification\TransactionNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Notations')]
class ReviewController extends AbstractController
{
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly SerializerInterface $serializer,
    private readonly ValidatorInterface $validator,
    private readonly TokenStorageInterface $tokenStorage,
    private readonly TransactionNotificationService $notificationService
  ) {
  }

  #[Route('/transactions/{id}/reviews', methods: ['POST'])]
  #[OA\Response(
    response: 201,
    description: 'Crée une évaluation pour une transaction',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Merci. Vous avez laissé un avis pour John Doe.'),
        new OA\Property(property: 'review', type: 'object', properties: [
          new OA\Property(property: 'id', type: 'integer', example: 1),
          new OA\Property(property: 'reviewer', type: 'object', ref: '#/components/schemas/User'),
          new OA\Property(property: 'reviewed', type: 'object', ref: '#/components/schemas/User'),
          new OA\Property(property: 'transaction', type: 'object', ref: '#/components/schemas/Transaction'),
          new OA\Property(property: 'productQualityRating', type: 'number', format: 'float', example: 4.5),
          new OA\Property(property: 'appointmentRespectRating', type: 'number', format: 'float', example: 4.0),
          new OA\Property(property: 'friendlinessRating', type: 'number', format: 'float', example: 5.0),
        ])
      ]
    )
  )]
  #[OA\Response(
    response: 400,
    description: 'Bad Request',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
      ]
    )
  )]
  #[OA\Response(
    response: 403,
    description: 'Forbidden',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Vous ne pouvez pas évaluer cette transaction')
      ]
    )
  )]
  #[OA\RequestBody(
    description: 'Données pour créer une évaluation',
    required: true,
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'productQualityRating', type: 'number', format: 'float', example: 4.5),
        new OA\Property(property: 'appointmentRespectRating', type: 'number', format: 'float', example: 4.0),
        new OA\Property(property: 'friendlinessRating', type: 'number', format: 'float', example: 5.0),
      ]
    )
  )]
  public function createReview(
    Request $request,
    Transaction $transaction,
  ): JsonResponse {
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    if (!$currentUser) {
      return $this->json([
        'success' => false,
        'message' => 'Vous devez être connecté pour évaluer une transaction'
      ], Response::HTTP_UNAUTHORIZED);
    }

    if (!$transaction->canBeReviewed()) {
      return $this->json([
        'success' => false,
        'message' => 'Cette transaction ne peut pas être évaluée'
      ], Response::HTTP_BAD_REQUEST);
    }

    $isBuyer = $transaction->getBuyer() === $currentUser;
    $isSeller = $transaction->getSeller() === $currentUser;

    if (!$isBuyer && !$isSeller) {
      return $this->json([
        'success' => false,
        'message' => 'Vous ne pouvez pas évaluer cette transaction'
      ], Response::HTTP_FORBIDDEN);
    }

    $existingReview = null;
    foreach ($transaction->getReviews() as $review) {
      if ($review->getReviewer() === $currentUser) {
        $existingReview = $review;
        break;
      }
    }

    if ($existingReview) {
      return $this->json([
        'success' => false,
        'message' => 'Vous avez déjà évalué cette transaction'
      ], Response::HTTP_BAD_REQUEST);
    }

    try {
      $review = new Review();
      $review->setReviewer($currentUser);

      if ($isBuyer) {
        $review->setReviewed($transaction->getSeller());
      } else {
        $review->setReviewed($transaction->getBuyer());
      }

      $review->setTransaction($transaction);

      $this->serializer->deserialize(
        $request->getContent(),
        Review::class,
        JsonEncoder::FORMAT,
        [
          AbstractNormalizer::OBJECT_TO_POPULATE => $review,
          AbstractNormalizer::GROUPS => ['review:write'],
        ]
      );

      $errors = $this->validator->validate($review);
      if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
          $errorMessages[$error->getPropertyPath()] = $error->getMessage();
        }

        return $this->json([
          'success' => false,
          'message' => 'Erreur de validation',
          'errors' => $errorMessages
        ], Response::HTTP_BAD_REQUEST);
      }

      $this->entityManager->persist($review);
      $transaction->addReview($review);

      $this->entityManager->flush();

      $this->updateUserRating($review->getReviewed());

      // Return success response with review
      return $this->json(
        [
          'success' => true,
          'message' => sprintf('Merci. Vous avez laissé un avis pour %s.', $review->getReviewed()->getFullName()),
          'review' => $review
        ],
        Response::HTTP_CREATED,
        [],
        ['groups' => ['review:read']]
      );
    } catch (\Exception $e) {
      return $this->json([
        'success' => false,
        'message' => 'Erreur lors de la désérialisation: ' . $e->getMessage()
      ], Response::HTTP_BAD_REQUEST);
    }
  }

  #[Route('/transactions/{id}/reviews', methods: ['GET'])]
  #[OA\Response(
    response: 200,
    description: 'Liste des évaluations d\'une transaction',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: '#/components/schemas/Review')
    )
  )]
  #[OA\Response(
    response: 401,
    description: 'Non authentifié',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'message', type: 'string', example: 'User not authenticated')
      ]
    )
  )]
  public function getTransactionReviews(Transaction $transaction): JsonResponse
  {
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    if (!$currentUser) {
      return $this->json(['message' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
    }

    $reviews = $transaction->getReviews();

    return $this->json(
      $reviews,
      Response::HTTP_OK,
      [],
      ['groups' => ['review:read']]
    );
  }

  #[Route('/users/{id}/reviews', methods: ['GET'])]
  #[OA\Response(
    response: 200,
    description: 'Liste des évaluations d\'un utilisateur',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: '#/components/schemas/Review')
    )
  )]
  public function getUserReviews(User $user, ReviewRepository $reviewRepository): JsonResponse
  {
    $reviews = $reviewRepository->findApprovedReviewsForUser($user);

    return $this->json(
      $reviews,
      Response::HTTP_OK,
      [],
      ['groups' => ['review:read']]
    );
  }

  #[Route('/users/{id}/ratings', methods: ['GET'])]
  #[OA\Response(
    response: 200,
    description: 'Moyennes des évaluations d\'un utilisateur',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'avgProductQuality', type: 'number', format: 'float', example: 4.5),
        new OA\Property(property: 'avgAppointmentRespect', type: 'number', format: 'float', example: 4.0),
        new OA\Property(property: 'avgFriendliness', type: 'number', format: 'float', example: 5.0),
        new OA\Property(property: 'avgOverall', type: 'number', format: 'float', example: 4.5),
        new OA\Property(property: 'totalReviews', type: 'integer', example: 10)
      ]
    )
  )]
  public function getUserRatings(User $user, ReviewRepository $reviewRepository): JsonResponse
  {
    $ratings = $reviewRepository->findAverageRatingsForUser($user);

    return $this->json($ratings, Response::HTTP_OK);
  }


  #[Route('/reviews/{id}/report', methods: ['POST'])]
  #[OA\RequestBody(
    description: 'Données pour signaler une évaluation',
    required: true,
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'reason', type: 'string', example: 'Cette évaluation contient des propos inappropriés')
      ]
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'Évaluation signalée avec succès',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Évaluation signalée avec succès')
      ]
    )
  )]
  #[OA\Response(
    response: 400,
    description: 'Requête invalide',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string')
      ]
    )
  )]
  #[OA\Response(
    response: 404,
    description: 'Évaluation non trouvée',
    content: new OA\JsonContent(
      properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Évaluation non trouvée')
      ]
    )
  )]
  public function reportReview(
    Request $request,
    Review $review
  ): JsonResponse {
    try {
      $data = json_decode($request->getContent(), true);

      if (!$data) {
        return $this->json([
          'success' => false,
          'message' => 'Format JSON invalide'
        ], Response::HTTP_BAD_REQUEST);
      }

      if ($review->isPending()) {
        $review->setStatus(ReviewStatus::NEED_VERIFICATION);
        $review->setModerationComment('Signalée: ' . ($data['reason'] ?? 'Aucune raison fournie'));

        $this->entityManager->flush();
      }

      return $this->json([
        'success' => true,
        'message' => 'Évaluation signalée avec succès'
      ], Response::HTTP_OK);
    } catch (\Exception $e) {
      return $this->json([
        'success' => false,
        'message' => 'Erreur lors du signalement: ' . $e->getMessage()
      ], Response::HTTP_BAD_REQUEST);
    }
  }

  private function updateUserRating(User $user): void
  {
    $reviewRepository = $this->entityManager->getRepository(Review::class);
    $ratings = $reviewRepository->findAverageRatingsForUser($user);

    $user->setAverageRating($ratings['avgOverall']);
    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }
}
