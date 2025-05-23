<?php

namespace App\Entity;

use App\Repository\FoodPreferenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FoodPreferenceRepository::class)]
class FoodPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['food_preference:read', 'user:read', 'user:profile', 'offer:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['food_preference:read', 'food_preference:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(
        max: 50,
        maxMessage: 'The food preference must be at most {{ limit }} characters long',
    )]
    private ?string $name = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'food_preferences')]
    private Collection $user_foodPreference;

    /**
     * @var Collection<int, Offer>
     */
    #[ORM\ManyToMany(targetEntity: Offer::class, mappedBy: 'food_preferences')]
    private Collection $offers;

    public function __construct()
    {
        $this->user_foodPreference = new ArrayCollection();
        $this->offers = new ArrayCollection();
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

    /**
     * @return Collection<int, User>
     */
    public function getUserFoodPreference(): Collection
    {
        return $this->user_foodPreference;
    }

    public function addUserFoodPreference(User $userFoodPreference): static
    {
        if (!$this->user_foodPreference->contains($userFoodPreference)) {
            $this->user_foodPreference->add($userFoodPreference);
            $userFoodPreference->addFoodPreference($this);
        }

        return $this;
    }

    public function removeUserFoodPreference(User $userFoodPreference): static
    {
        if ($this->user_foodPreference->removeElement($userFoodPreference)) {
            $userFoodPreference->removeFoodPreference($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Offer>
     */
    public function getOffer(): Collection
    {
        return $this->offers;
    }

    public function addOffer(Offer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->addFoodPreference($this);
        }

        return $this;
    }

    public function removeOffer(Offer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            $offer->removeFoodPreference($this);
        }

        return $this;
    }
}
