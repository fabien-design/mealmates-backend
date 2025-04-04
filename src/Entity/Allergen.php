<?php

namespace App\Entity;

use App\Repository\AllergenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AllergenRepository::class)]
class Allergen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['allergen:read', 'user:read', 'user:profile'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['allergen:read', 'allergen:write', 'user:read', 'user:profile'])]
    #[Assert\NotBlank]
    #[Assert\Length(
        max: 50,
        maxMessage: 'The allergen must be at most {{ limit }} characters long',
    )]
    private ?string $name = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'allergen')]
    #[Groups(['allergen:read'])]
    private Collection $User_allergen;

    /**
     * @var Collection<int, Offer>
     */
    #[ORM\ManyToMany(targetEntity: Offer::class, mappedBy: 'allergens')]
    private Collection $offers;

    public function __construct()
    {
        $this->User_allergen = new ArrayCollection();
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
            $offer->addAllergen($this);
        }

        return $this;
    }

    public function removeOffer(Offer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            $offer->removeAllergen($this);
        }

        return $this;
    }
}
