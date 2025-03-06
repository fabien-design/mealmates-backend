<?php

namespace App\Entity;

use App\Repository\AllergeneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AllergeneRepository::class)]
class Allergene
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
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'id_allergene')]
    private Collection $User_allergene;

    public function __construct()
    {
        $this->User_allergene = new ArrayCollection();
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
    public function getUserAllergene(): Collection
    {
        return $this->User_allergene;
    }

    public function addUserAllergene(User $userAllergene): static
    {
        if (!$this->User_allergene->contains($userAllergene)) {
            $this->User_allergene->add($userAllergene);
            $userAllergene->addIdAllergene($this);
        }

        return $this;
    }

    public function removeUserAllergene(User $userAllergene): static
    {
        if ($this->User_allergene->removeElement($userAllergene)) {
            $userAllergene->removeIdAllergene($this);
        }

        return $this;
    }
}
