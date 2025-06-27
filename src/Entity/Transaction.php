<?php

namespace App\Entity;

use App\Enums\TransactionStatus;
use App\Repository\TransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['transaction:read', 'offer:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:read'])]
    private ?Offer $offer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:read'])]
    private ?User $buyer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:read'])]
    private ?User $seller = null;

    #[ORM\Column]
    #[Groups(['transaction:read', 'offer:read'])]
    private ?float $amount = null;

    #[ORM\Column(length: 20, enumType: TransactionStatus::class)]
    #[Groups(['transaction:read', 'offer:read'])]
    private ?TransactionStatus $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeTransferId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeRefundId = null;

    #[ORM\Column]
    #[Groups(['transaction:read', 'offer:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['transaction:read'])]
    private ?\DateTimeImmutable $transferredAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $refundedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['transaction:read'])]
    private ?\DateTimeImmutable $reservationExpiresAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['transaction:read'])]
    private ?\DateTimeImmutable $reservedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCodeToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $qrCodeExpiresAt = null;

    #[ORM\OneToOne(mappedBy: 'transaction', cascade: ['persist', 'remove'])]
    #[Groups(['transaction:read'])]
    private ?Review $buyerReview = null;
    
    #[ORM\OneToOne(mappedBy: 'transaction', cascade: ['persist', 'remove'])]
    #[Groups(['transaction:read'])]
    private ?Review $sellerReview = null;
    
    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: Review::class, orphanRemoval: true)]
    private Collection $reviews;
    
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffer(): ?Offer
    {
        return $this->offer;
    }

    public function setOffer(?Offer $offer): static
    {
        $this->offer = $offer;
        return $this;
    }

    public function getBuyer(): ?User
    {
        return $this->buyer;
    }

    public function setBuyer(?User $buyer): static
    {
        $this->buyer = $buyer;
        return $this;
    }

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function setSeller(?User $seller): static
    {
        $this->seller = $seller;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmountWithFees(): float
    {
        $feePercentage = (float)$_ENV['SERVICE_FEES'] ?? 0.0;

        return $this->amount * (1 - $feePercentage);
    }

    public function getStatus(): ?TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getStripeTransferId(): ?string
    {
        return $this->stripeTransferId;
    }

    public function setStripeTransferId(?string $stripeTransferId): static
    {
        $this->stripeTransferId = $stripeTransferId;
        return $this;
    }

    public function getStripeRefundId(): ?string
    {
        return $this->stripeRefundId;
    }

    public function setStripeRefundId(?string $stripeRefundId): static
    {
        $this->stripeRefundId = $stripeRefundId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getTransferredAt(): ?\DateTimeImmutable
    {
        return $this->transferredAt;
    }

    public function setTransferredAt(?\DateTimeImmutable $transferredAt): static
    {
        $this->transferredAt = $transferredAt;
        return $this;
    }

    public function getRefundedAt(): ?\DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function setRefundedAt(?\DateTimeImmutable $refundedAt): static
    {
        $this->refundedAt = $refundedAt;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getReservationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->reservationExpiresAt;
    }

    public function setReservationExpiresAt(?\DateTimeImmutable $reservationExpiresAt): static
    {
        $this->reservationExpiresAt = $reservationExpiresAt;
        return $this;
    }

    public function getReservedAt(): ?\DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function setReservedAt(?\DateTimeImmutable $reservedAt): static
    {
        $this->reservedAt = $reservedAt;
        return $this;
    }

    public function getQrCodeToken(): ?string
    {
        return $this->qrCodeToken;
    }

    public function setQrCodeToken(?string $qrCodeToken): static
    {
        $this->qrCodeToken = $qrCodeToken;
        return $this;
    }

    public function getQrCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->qrCodeExpiresAt;
    }

    public function setQrCodeExpiresAt(?\DateTimeImmutable $qrCodeExpiresAt): static
    {
        $this->qrCodeExpiresAt = $qrCodeExpiresAt;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::PENDING;
    }

    public function isReserved(): bool
    {
        return $this->status === TransactionStatus::RESERVED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === TransactionStatus::CONFIRMED;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === TransactionStatus::FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === TransactionStatus::REFUNDED;
    }

    public function isFree(): bool
    {
        return $this->amount === 0.0;
    }

    public function isReservationExpired(): bool
    {
        if ($this->status !== TransactionStatus::RESERVED || $this->reservationExpiresAt === null) {
            return false;
        }

        return $this->reservationExpiresAt < new \DateTimeImmutable();
    }

    public function isQrCodeExpired(): bool
    {
        if ($this->qrCodeExpiresAt === null) {
            return true;
        }

        return $this->qrCodeExpiresAt < new \DateTimeImmutable();
    }
    
    public function getBuyerReview(): ?Review
    {
        return $this->buyerReview;
    }

    public function setBuyerReview(?Review $buyerReview): static
    {
        $this->buyerReview = $buyerReview;
        return $this;
    }

    public function getSellerReview(): ?Review
    {
        return $this->sellerReview;
    }

    public function setSellerReview(?Review $sellerReview): static
    {
        $this->sellerReview = $sellerReview;
        return $this;
    }
    
    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setTransaction($this);
            
            // Automatically set the appropriate review type based on the reviewer
            if ($review->getReviewer() === $this->getBuyer()) {
                $this->setBuyerReview($review);
            } elseif ($review->getReviewer() === $this->getSeller()) {
                $this->setSellerReview($review);
            }
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getTransaction() === $this) {
                $review->setTransaction(null);
            }
            
            // Remove specific review references
            if ($this->buyerReview === $review) {
                $this->buyerReview = null;
            } elseif ($this->sellerReview === $review) {
                $this->sellerReview = null;
            }
        }

        return $this;
    }
    
    public function canBeReviewed(): bool
    {
        return $this->status === TransactionStatus::COMPLETED;
    }
}
