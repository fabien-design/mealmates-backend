<?php

namespace App\Entity;

use App\Enums\ReviewStatus;
use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['review:read', 'user:show', 'transaction:read', 'offer:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reviewsGiven')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read:reviewer', 'offer:read'])]
    private ?User $reviewer = null;

    #[ORM\ManyToOne(inversedBy: 'reviewsReceived')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read', 'offer:read'])]
    private ?User $reviewed = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read'])]
    private ?Transaction $transaction = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['review:read', 'user:show', 'review:write', 'transaction:read', 'user:read'])]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.',
    )]
    private ?float $productQualityRating = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['review:read', 'user:show', 'review:write', 'transaction:read', 'user:read'])]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.',
    )]
    private ?float $appointmentRespectRating = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['review:read', 'user:show', 'review:write', 'transaction:read', 'user:read'])]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.',
    )]
    private ?float $friendlinessRating = null;

    #[ORM\Column]
    #[Groups(['review:read', 'user:show'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20, enumType: ReviewStatus::class)]
    #[Groups(['review:read', 'transaction:read', 'offer:read'])]
    private ?ReviewStatus $status = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['review:read'])]
    private ?\DateTimeImmutable $moderatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['review:read', 'admin:read'])]
    private ?string $moderationComment = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = ReviewStatus::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function setReviewer(?User $reviewer): static
    {
        $this->reviewer = $reviewer;
        return $this;
    }

    public function getReviewed(): ?User
    {
        return $this->reviewed;
    }

    public function setReviewed(?User $reviewed): static
    {
        $this->reviewed = $reviewed;
        return $this;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function getProductQualityRating(): ?float
    {
        return $this->productQualityRating;
    }

    public function setProductQualityRating(?float $productQualityRating): static
    {
        $this->productQualityRating = $productQualityRating;
        return $this;
    }

    public function getAppointmentRespectRating(): ?float
    {
        return $this->appointmentRespectRating;
    }

    public function setAppointmentRespectRating(?float $appointmentRespectRating): static
    {
        $this->appointmentRespectRating = $appointmentRespectRating;
        return $this;
    }

    public function getFriendlinessRating(): ?float
    {
        return $this->friendlinessRating;
    }

    public function setFriendlinessRating(?float $friendlinessRating): static
    {
        $this->friendlinessRating = $friendlinessRating;
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

    public function getStatus(): ?ReviewStatus
    {
        return $this->status;
    }

    public function setStatus(ReviewStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getModeratedAt(): ?\DateTimeImmutable
    {
        return $this->moderatedAt;
    }

    public function setModeratedAt(?\DateTimeImmutable $moderatedAt): static
    {
        $this->moderatedAt = $moderatedAt;
        return $this;
    }

    public function getModerationComment(): ?string
    {
        return $this->moderationComment;
    }

    public function setModerationComment(?string $moderationComment): static
    {
        $this->moderationComment = $moderationComment;
        return $this;
    }

    #[Groups(['review:read', 'user:show', 'transaction:read', 'offer:read'])]
    public function getAverageRating(): ?float
    {
        $ratings = [];

        if ($this->productQualityRating !== null) {
            $ratings[] = $this->productQualityRating;
        }

        if ($this->appointmentRespectRating !== null) {
            $ratings[] = $this->appointmentRespectRating;
        }

        if ($this->friendlinessRating !== null) {
            $ratings[] = $this->friendlinessRating;
        }

        if (empty($ratings)) {
            return null;
        }

        return array_sum($ratings) / count($ratings);
    }

    #[Groups(['review:read'])]
    public function getOffer(): ?Offer
    {
        return $this->transaction?->getOffer();
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === ReviewStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ReviewStatus::REJECTED;
    }
}
