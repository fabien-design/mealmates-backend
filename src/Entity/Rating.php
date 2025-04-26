<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $rater_user_id = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $rated_user_id = null;

    #[ORM\Column(length: 50)]
    private ?string $situation = null;

    #[ORM\Column(nullable: true)]
    private ?int $quality = null;

    #[ORM\Column(nullable: true)]
    private ?int $punctuality = null;

    #[ORM\Column(nullable: true)]
    private ?int $friendliness = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRaterUserId(): ?user
    {
        return $this->rater_user_id;
    }

    public function setRaterUserId(?user $rater_user_id): static
    {
        $this->rater_user_id = $rater_user_id;

        return $this;
    }

    public function getRatedUserId(): ?user
    {
        return $this->rated_user_id;
    }

    public function setRatedUserId(?user $rated_user_id): static
    {
        $this->rated_user_id = $rated_user_id;

        return $this;
    }

    public function getSituation(): ?string
    {
        return $this->situation;
    }

    public function setSituation(string $situation): static
    {
        $this->situation = $situation;

        return $this;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function setQuality(?int $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function getPunctuality(): ?int
    {
        return $this->punctuality;
    }

    public function setPunctuality(?int $punctuality): static
    {
        $this->punctuality = $punctuality;

        return $this;
    }

    public function getFriendliness(): ?int
    {
        return $this->friendliness;
    }

    public function setFriendliness(?int $friendliness): static
    {
        $this->friendliness = $friendliness;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }
}
