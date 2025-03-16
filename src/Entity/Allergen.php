<?php

namespace App\Entity;

use App\Repository\AllergenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AllergenRepository::class)]
class Allergen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'allergen')]
    private Collection $User_allergen;

    public function __construct()
    {
        $this->User_allergen = new ArrayCollection();
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
    public function getUserAllergen(): Collection
    {
        return $this->User_allergen;
    }

    public function addUserAllergen(User $userAllergen): static
    {
        if (!$this->User_allergen->contains($userAllergen)) {
            $this->User_allergen->add($userAllergen);
            $userAllergen->addAllergen($this);
        }

        return $this;
    }

    public function removeUserAllergen(User $userAllergen): static
    {
        if ($this->User_allergen->removeElement($userAllergen)) {
            $userAllergen->removeAllergen($this);
        }

        return $this;
    }
}
