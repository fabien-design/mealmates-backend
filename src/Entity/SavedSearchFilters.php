<?php

namespace App\Entity;

use App\Repository\SavedSearchFiltersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SavedSearchFiltersRepository::class)]
class SavedSearchFilters
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['saved_search:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 55)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\NotBlank(message: "Le nom ne peut pas être vide")]
    #[Assert\Length(max: 55, maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères")]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9\s]+$/",
        message: "Le nom ne peut contenir que des lettres, des chiffres et des espaces"
    )]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: "La latitude doit être comprise entre {{ min }} et {{ max }}")]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: "La longitude doit être comprise entre {{ min }} et {{ max }}")]
    private ?float $longitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\Range(min: 100, max: 50000, notInRangeMessage: "Le rayon doit être compris entre {{ min }} et {{ max }} mètres")]
    private ?int $radius = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    private ?array $productTypes = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\Choice(choices: ["today", "tomorrow", "week"], message: "La valeur doit être l'une des suivantes : today, tomorrow, week")]
    private ?string $expirationDate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\PositiveOrZero(message: "Le prix minimum ne peut pas être négatif")]
    private ?float $minPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\Positive(message: "Le prix maximum doit être positif")]
    private ?float $maxPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    #[Assert\Range(min: 0, max: 5, notInRangeMessage: "La note minimale du vendeur doit être comprise entre {{ min }} et {{ max }}")]
    private ?float $minSellerRating = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['saved_search:read', 'saved_search:write'])]
    private ?array $dietaryPreferences = null;

    #[ORM\ManyToOne(inversedBy: 'savedSearchFilters')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['saved_search:read'])]
    private ?User $user = null;

    public function __construct()
    {
        $this->productTypes = [];
        $this->dietaryPreferences = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getRadius(): ?int
    {
        return $this->radius;
    }

    public function setRadius(?int $radius): static
    {
        $this->radius = $radius;

        return $this;
    }

    public function getProductTypes(): ?array
    {
        return $this->productTypes;
    }

    public function setProductTypes(?array $productTypes): static
    {
        $this->productTypes = $productTypes;

        return $this;
    }

    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?string $expirationDate): static
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getMinPrice(): ?float
    {
        return $this->minPrice;
    }

    public function setMinPrice(?float $minPrice): static
    {
        $this->minPrice = $minPrice;

        return $this;
    }

    public function getMaxPrice(): ?float
    {
        return $this->maxPrice;
    }

    public function setMaxPrice(?float $maxPrice): static
    {
        $this->maxPrice = $maxPrice;

        return $this;
    }

    public function getMinSellerRating(): ?float
    {
        return $this->minSellerRating;
    }

    public function setMinSellerRating(?float $minSellerRating): static
    {
        $this->minSellerRating = $minSellerRating;

        return $this;
    }

    public function getDietaryPreferences(): ?array
    {
        return $this->dietaryPreferences;
    }

    public function setDietaryPreferences(?array $dietaryPreferences): static
    {
        $this->dietaryPreferences = $dietaryPreferences;

        return $this;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
