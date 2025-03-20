<?php

namespace App\Entity;

use App\Repository\OffersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OffersRepository::class)]
class Offers
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    private ?float $dynamicPrice = null;

    #[ORM\Column(type: Types::JSON)]
    private ?string $timeslots = [];

    #[ORM\Column]
    private ?bool $hasBeenSold = null;

    #[ORM\Column(type: Types::ARRAY )]
    private array $diet = [];

    #[ORM\Column(type: Types::ARRAY )]
    private array $allergens = [];

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeInterface $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDynamicPrice(): ?float
    {
        return $this->dynamicPrice;
    }

    public function setDynamicPrice(?float $dynamicPrice): static
    {
        $this->dynamicPrice = $dynamicPrice;

        return $this;
    }

    public function getTimeslots(): ?string
    {
        return $this->timeslots;
    }

    public function setTimeslots(string $timeslots): static
    {
        $this->timeslots = $timeslots;

        return $this;
    }

    public function hasBeenSold(): ?bool
    {
        return $this->hasBeenSold;
    }

    public function setHasBeenSold(bool $hasBeenSold): static
    {
        $this->hasBeenSold = $hasBeenSold;

        return $this;
    }

    public function getDiet(): array
    {
        return $this->diet;
    }

    public function setDiet(array $diet): static
    {
        $this->diet = $diet;

        return $this;
    }

    public function getAllergens(): array
    {
        return $this->allergens;
    }

    public function setAllergens(array $allergens): static
    {
        $this->allergens = $allergens;

        return $this;
    }
}
