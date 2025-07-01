<?php

namespace App\Entity;

use App\Repository\UserBadgeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
class UserBadge
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  #[Groups(['userbadge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'userBadges')]
  #[ORM\JoinColumn(nullable: false)]
  #[Groups(['userbadge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?User $user = null;

  #[ORM\ManyToOne(inversedBy: 'userBadges')]
  #[ORM\JoinColumn(nullable: false)]
  #[Groups(['userbadge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?Badge $badge = null;

  #[ORM\Column]
  #[Groups(['userbadge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?\DateTimeImmutable $awardedAt = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['userbadge:read', 'user:read', 'user:profile'])]
  private ?int $currentProgress = null;

  public function __construct()
  {
    $this->awardedAt = new \DateTimeImmutable();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getUser(): ?User
  {
    return $this->user;
  }

  public function setUser(?User $user): static
  {
    $this->user = $user;

    return $this;
  }

  public function getBadge(): ?Badge
  {
    return $this->badge;
  }

  public function setBadge(?Badge $badge): static
  {
    $this->badge = $badge;

    return $this;
  }

  public function getAwardedAt(): ?\DateTimeImmutable
  {
    return $this->awardedAt;
  }

  public function setAwardedAt(\DateTimeImmutable $awardedAt): static
  {
    $this->awardedAt = $awardedAt;

    return $this;
  }

  public function getCurrentProgress(): ?int
  {
    return $this->currentProgress;
  }

  public function setCurrentProgress(?int $currentProgress): static
  {
    $this->currentProgress = $currentProgress;

    return $this;
  }
}
