<?php

namespace App\EventSubscriber;

use App\Entity\Offer;
use App\Entity\Review;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Enums\TransactionStatus;
use App\Service\GamificationService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\User\UserInterface;

class GamificationSubscriber implements EventSubscriber
{
  private GamificationService $gamificationService;

  public function __construct(GamificationService $gamificationService)
  {
    $this->gamificationService = $gamificationService;
  }

  public function getSubscribedEvents(): array
  {
    return [
      Events::postPersist,
      Events::postUpdate,
    ];
  }

  public function postPersist(PostPersistEventArgs $args): void
  {
    $entity = $args->getObject();

    if ($entity instanceof Offer) {
      $this->handleOfferCreation($entity);
    } elseif ($entity instanceof Transaction) {
      $this->handleTransactionCreation($entity);
    } elseif ($entity instanceof Review) {
      $this->handleReviewCreation($entity);
    }
  }

  public function postUpdate(PostUpdateEventArgs $args): void
  {
    $entity = $args->getObject();

    if ($entity instanceof Transaction) {
      $this->handleTransactionUpdate($entity);
    } elseif ($entity instanceof Review) {
      $this->handleReviewUpdate($entity);
    }
  }

  private function handleOfferCreation(Offer $offer): void
  {
    $seller = $offer->getSeller();
    if ($seller instanceof User) {
      $this->gamificationService->processOfferCreated($seller);
    }
  }

  private function handleTransactionCreation(Transaction $transaction): void
  {
    // Nothing to do here, we'll handle transactions in the update method
  }

  private function handleTransactionUpdate(Transaction $transaction): void
  {
    // Check if transaction was just completed
    if ($transaction->isCompleted()) {
      $buyer = $transaction->getBuyer();
      $seller = $transaction->getSeller();
      $offer = $transaction->getOffer();

      if ($buyer instanceof User && $seller instanceof User && $offer) {
        // Award credits to both buyer and seller
        $this->gamificationService->processTransactionCompleted($buyer);
        $this->gamificationService->processTransactionCompleted($seller);

        // Process food saved for the seller
        $this->gamificationService->processFoodSaved($seller, $offer->getQuantity() ?? 1);
      }
    }
  }

  private function handleReviewCreation(Review $review): void
  {
    $reviewer = $review->getReviewer();
    if ($reviewer instanceof User) {
      // Award credits to reviewer for writing a review
      $this->gamificationService->processReviewGiven($reviewer);
    }
  }

  private function handleReviewUpdate(Review $review): void
  {
    // Check if review was just approved
    if ($review->isApproved()) {
      $reviewed = $review->getReviewed();
      if ($reviewed instanceof User) {
        $this->gamificationService->processReviewReceived($reviewed);
      }
    }
  }
}
