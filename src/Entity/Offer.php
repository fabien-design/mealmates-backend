<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups('offer:read')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups('offer:read')]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups('offer:read')]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups('offer:read')]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups('offer:read')]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column]
    #[Groups('offer:read')]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    #[Groups('offer:read')]
    private ?float $dynamicPrice = null;

    #[ORM\Column]
    #[Groups('offer:read')]
    private ?bool $hasBeenSold = null;

    /**
     * @var Collection<int, Allergen>
     */
    #[ORM\ManyToMany(targetEntity: Allergen::class, inversedBy: 'offers')]
    #[Groups('offer:read')]
    private Collection $allergens;

    /**
     * @var Collection<int, FoodPreference>
     */
    #[ORM\ManyToMany(targetEntity: FoodPreference::class, inversedBy: 'offers')]
    #[Groups('offer:read')]
    private Collection $food_preferences;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('offer:read')]
    private ?User $seller = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[Groups('offer:read')]
    private ?User $buyer = null;

    #[ORM\Column]
    #[Groups('offer:read')]
    private ?bool $isRecurring = null;

    /**
     * @var Collection<int, Image>
     */
    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'offer', orphanRemoval: true)]
    #[Groups('offer:read')]
    private Collection $images;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('offer:read')]
    private ?Address $address = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $expiryAlertSent = null;

    public function __construct()
    {
        $this->allergens = new ArrayCollection();
        $this->food_preferences = new ArrayCollection();
        $this->images = new ArrayCollection();
        if(!isset($this->expiryAlertSent)) {
            $this->expiryAlertSent = false;
        }
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

    public function hasBeenSold(): ?bool
    {
        return $this->hasBeenSold;
    }

    public function setHasBeenSold(bool $hasBeenSold): static
    {
        $this->hasBeenSold = $hasBeenSold;

        return $this;
    }

    /**
     * @return Collection<int, Allergen>
     */
    public function getAllergens(): Collection
    {
        return $this->allergens;
    }

    public function addAllergen(Allergen $allergen): static
    {
        if (!$this->allergens->contains($allergen)) {
            $this->allergens->add($allergen);
        }

        return $this;
    }

    public function removeAllergen(Allergen $allergen): static
    {
        $this->allergens->removeElement($allergen);

        return $this;
    }

    /**
     * @return Collection<int, FoodPreference>
     */
    public function getFoodPreferences(): Collection
    {
        return $this->food_preferences;
    }

    public function addFoodPreference(FoodPreference $foodPreference): static
    {
        if (!$this->food_preferences->contains($foodPreference)) {
            $this->food_preferences->add($foodPreference);
        }

        return $this;
    }

    public function removeFoodPreference(FoodPreference $foodPreference): static
    {
        $this->food_preferences->removeElement($foodPreference);

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

    public function getBuyer(): ?User
    {
        return $this->buyer;
    }

    public function setBuyer(?User $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function isRecurring(): ?bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setOffer($this);
        }

        return $this;
    }

    public function removeImage(Image $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getOffer() === $this) {
                $image->setOffer(null);
            }
        }

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function isExpiryAlertSent(): ?bool
    {
        return $this->expiryAlertSent;
    }

    public function setExpiryAlertSent(bool $expiryAlertSent): static
    {
        $this->expiryAlertSent = $expiryAlertSent;

        return $this;
    }
}
