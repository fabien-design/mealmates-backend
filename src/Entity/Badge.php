<?php

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
class Badge
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  #[Groups(['badge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?int $id = null;

  #[ORM\Column(length: 50)]
  #[Groups(['badge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?string $name = null;

  #[ORM\Column(length: 255)]
  #[Groups(['badge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?string $description = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['badge:read', 'user:read', 'user:profile', 'user:show'])]
  private ?string $icon = null;

  #[ORM\Column(length: 50)]
  private ?string $type = null;

  #[ORM\Column(nullable: true)]
  private ?int $threshold = null;

  /**
   * @var Collection<int, UserBadge>
   */
  #[ORM\OneToMany(mappedBy: 'badge', targetEntity: UserBadge::class, orphanRemoval: true)]
  private Collection $userBadges;

  public function __construct()
  {
    $this->userBadges = new ArrayCollection();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getName(): ?string
  {
    return $this->name;
  }

  public function setName(string $name): static
  {
    $this->name = $name;

    return $this;
  }

  public function getDescription(): ?string
  {
    return $this->description;
  }

  public function setDescription(string $description): static
  {
    $this->description = $description;

    return $this;
  }

  public function getIcon(): ?string
  {
    return $this->icon;
  }

  public function setIcon(?string $icon): static
  {
    $this->icon = $icon;

    return $this;
  }

  public function getType(): ?string
  {
    return $this->type;
  }

  public function setType(string $type): static
  {
    $this->type = $type;

    return $this;
  }

  public function getThreshold(): ?int
  {
    return $this->threshold;
  }

  public function setThreshold(?int $threshold): static
  {
    $this->threshold = $threshold;

    return $this;
  }

  /**
   * @return Collection<int, UserBadge>
   */
  public function getUserBadges(): Collection
  {
    return $this->userBadges;
  }

  public function addUserBadge(UserBadge $userBadge): static
  {
    if (!$this->userBadges->contains($userBadge)) {
      $this->userBadges->add($userBadge);
      $userBadge->setBadge($this);
    }

    return $this;
  }

  public function removeUserBadge(UserBadge $userBadge): static
  {
    if ($this->userBadges->removeElement($userBadge)) {
      // set the owning side to null (unless already changed)
      if ($userBadge->getBadge() === $this) {
        $userBadge->setBadge(null);
      }
    }

    return $this;
  }

  public function __toString(): string
  {
    return $this->name ?? '';
  }
}
