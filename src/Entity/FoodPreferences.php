<?php

namespace App\Entity;

use App\Repository\FoodPreferencesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FoodPreferencesRepository::class)]
class FoodPreferences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'food_preferences')]
    private Collection $user_foodPreferences;

    public function __construct()
    {
        $this->user_foodPreferences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUserFoodPreferences(): Collection
    {
        return $this->user_foodPreferences;
    }

    public function addUserFoodPreference(User $userFoodPreference): static
    {
        if (!$this->user_foodPreferences->contains($userFoodPreference)) {
            $this->user_foodPreferences->add($userFoodPreference);
            $userFoodPreference->addFoodPreference($this);
        }

        return $this;
    }

    public function removeUserFoodPreference(User $userFoodPreference): static
    {
        if ($this->user_foodPreferences->removeElement($userFoodPreference)) {
            $userFoodPreference->removeFoodPreference($this);
        }

        return $this;
    }
}
