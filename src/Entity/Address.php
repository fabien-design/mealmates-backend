<?php

namespace App\Entity;

use App\Repository\AddressRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['address:read', 'user:read', 'user:profile', 'offer:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['address:read', 'address:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(
        max: 50,
        maxMessage: 'The city must be at most {{ limit }} characters long',
    )]
    private ?string $city = null;

    #[ORM\Column(length: 50)]
    #[Groups(['address:read', 'address:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(
        max: 50,
        maxMessage: 'The zip code must be at most {{ limit }} characters long',
    )]
    private ?string $zipCode = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(
        max: 100,
        maxMessage: 'The address must be at most {{ limit }} characters long',
    )]
    private ?string $address = null;

    #[ORM\Column(length: 50)]
    #[Groups(['address:read', 'address:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(
        max: 50,
        maxMessage: 'The region must be at most {{ limit }} characters long',
    )]
    private ?string $region = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'address')]
    private Collection $id_user;

    /**
     * @var Collection<int, Offer>
     */
    #[ORM\OneToMany(targetEntity: Offer::class, mappedBy: 'address')]
    private Collection $offers;

    #[ORM\Column]
    #[Groups(['address:read', 'address:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Range(
        min: -180,
        max: 180,
        notInRangeMessage: 'The longitude must be between {{ min }} and {{ max }}',
    )]
    #[Assert\Type(
        type: 'float',
        message: 'The longitude must be a valid float number',
    )]
    private ?float $longitude = null;

    #[ORM\Column]
    #[Groups(['address:read', 'address:write', 'user:read', 'user:profile', 'offer:read'])]
    #[Assert\NotBlank]
    #[Assert\Range(
        min: -90,
        max: 90,
        notInRangeMessage: 'The latitude must be between {{ min }} and {{ max }}',
    )]
    #[Assert\Type(
        type: 'float',
        message: 'The latitude must be a valid float number',
    )]
    private ?float $latitude = null;

    public function __construct()
    {
        $this->id_user = new ArrayCollection();
        $this->offers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getIdUser(): Collection
    {
        return $this->id_user;
    }

    public function addIdUser(User $idUser): static
    {
        if (!$this->id_user->contains($idUser)) {
            $this->id_user->add($idUser);
        }

        return $this;
    }

    public function removeIdUser(User $idUser): static
    {
        $this->id_user->removeElement($idUser);

        return $this;
    }

    /**
     * @return Collection<int, Offer>
     */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(Offer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setAddress($this);
        }

        return $this;
    }

    public function removeOffer(Offer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            // set the owning side to null (unless already changed)
            if ($offer->getAddress() === $this) {
                $offer->setAddress(null);
            }
        }

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }
}
