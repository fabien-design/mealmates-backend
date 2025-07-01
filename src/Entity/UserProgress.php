<?php

namespace App\Entity;

use App\Repository\UserProgressRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserProgressRepository::class)]
class UserProgress
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  #[Groups(['userprogress:read', 'user:read', 'user:profile'])]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'progressTrackers')]
  #[ORM\JoinColumn(nullable: false)]
  private ?User $user = null;

  #[ORM\Column(length: 50)]
  #[Groups(['userprogress:read', 'user:read', 'user:profile'])]
  private ?string $progressType = null;

  #[ORM\Column]
  #[Groups(['userprogress:read', 'user:read', 'user:profile'])]
  private int $currentValue = 0;

  #[ORM\Column]
  #[Groups(['userprogress:read', 'user:read', 'user:profile'])]
  private ?\DateTimeImmutable $lastUpdated = null;

  public function __construct()
  {
    $this->lastUpdated = new \DateTimeImmutable();
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

  public function getProgressType(): ?string
  {
    return $this->progressType;
  }

  public function setProgressType(string $progressType): static
  {
    $this->progressType = $progressType;

    return $this;
  }

  public function getCurrentValue(): int
  {
    return $this->currentValue;
  }

  public function setCurrentValue(int $currentValue): static
  {
    $this->currentValue = $currentValue;

    return $this;
  }

  public function incrementValue(int $amount): static
  {
    $this->currentValue += $amount;
    $this->lastUpdated = new \DateTimeImmutable();

    return $this;
  }

  public function getLastUpdated(): ?\DateTimeImmutable
  {
    return $this->lastUpdated;
  }

  public function setLastUpdated(\DateTimeImmutable $lastUpdated): static
  {
    $this->lastUpdated = $lastUpdated;

    return $this;
  }
}
