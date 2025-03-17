<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'address:read', 'allergen:read', 'food_preference:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read', 'user:write', 'allergen:read', 'food_preference:read'])]
    #[Assert\NotBlank]
    #[Assert\Email(
        message: 'The email {{ value }} is not a valid email.',
    )]
    #[Assert\Length(
        max: 180,
        maxMessage: 'The email must be at most {{ limit }} characters long',
    )]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['user:read', 'user:write', 'admin:read'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['user:write'])]
    #[Assert\When(
        expression: 'this.getPassword() != null',
        constraints: [
            new Assert\NotBlank(message: "Le mot de passe ne peut pas être vide"),
            new Assert\Length(
                min: 8,
                minMessage: "Le mot de passe doit contenir au moins {{ limit }} caractères",
            ),
            new Assert\Regex(
                pattern: "/[A-Z]/",
                message: "Le mot de passe doit contenir au moins une lettre majuscule"
            ),
            new Assert\Regex(
                pattern: "/[a-z]/",
                message: "Le mot de passe doit contenir au moins une lettre minuscule"
            ),
            new Assert\Regex(
                pattern: "/[0-9]/",
                message: "Le mot de passe doit contenir au moins un chiffre"
            ),
            new Assert\Regex(
                pattern: "/[^A-Za-z0-9]/",
                message: "Le mot de passe doit contenir au moins un caractère spécial"
            )
        ]
    )]
    private ?string $password = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['user:read', 'user:write', 'user:profile'])]
    #[Assert\When(
        expression: 'this.getLastName() != null',
        constraints: [
            new Assert\Length(
                min: 2,
                max: 50,
                minMessage: "Le nom doit contenir au moins {{ limit }} caractères",
                maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères"
            )
        ]
    )]
    private ?string $last_name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read', 'user:write', 'user:profile'])]
    private ?bool $sexe = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['user:read', 'user:write', 'user:profile'])]
    #[Assert\When(
        expression: 'this.getLastName() != null',
        constraints: [
            new Assert\Length(
                min: 2,
                max: 50,
                minMessage: "Le prénom doit contenir au moins {{ limit }} caractères",
                maxMessage: "Le prénom ne peut pas dépasser {{ limit }} caractères"
            )
        ]
    )]
    private ?string $first_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $githubId = null;

    /**
     * @var Collection<int, Address>
     */
    #[ORM\ManyToMany(targetEntity: Address::class, mappedBy: 'id_user')]
    #[Groups(['user:read', 'user:profile'])]
    private Collection $address;

    /**
     * @var Collection<int, Allergen>
     */
    #[ORM\ManyToMany(targetEntity: Allergen::class, inversedBy: 'User_allergen')]
    #[Groups(['user:read', 'user:profile'])]
    private Collection $allergen;

    /**
     * @var Collection<int, FoodPreference>
     */
    #[ORM\ManyToMany(targetEntity: FoodPreference::class, inversedBy: 'user_foodPreference')]
    #[Groups(['user:read', 'user:profile'])]
    private Collection $food_preferences;

    #[ORM\Column]
    #[Groups(['user:read', 'admin:read'])]
    private bool $isVerified = false;

    public function __construct()
    {
        $this->address = new ArrayCollection();
        $this->allergen = new ArrayCollection();
        $this->food_preferences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): static
    {
        $this->last_name = $last_name;

        return $this;
    }

    public function isSexe(): ?bool
    {
        return $this->sexe;
    }

    public function setSexe(?bool $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): static
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(?string $facebookId): static
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getGithubId(): ?string
    {
        return $this->githubId;
    }

    public function setGithubId(?string $githubId): static
    {
        $this->githubId = $githubId;

        return $this;
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddress(): Collection
    {
        return $this->address;
    }

    public function addAddress(Address $address): static
    {
        if (!$this->address->contains($address)) {
            $this->address->add($address);
            $address->addIdUser($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): static
    {
        if ($this->address->removeElement($address)) {
            $address->removeIdUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Allergen>
     */
    public function getAllergen(): Collection
    {
        return $this->allergen;
    }

    public function addAllergen(Allergen $idAllergen): static
    {
        if (!$this->allergen->contains($idAllergen)) {
            $this->allergen->add($idAllergen);
        }

        return $this;
    }

    public function removeAllergen(Allergen $idAllergen): static
    {
        $this->allergen->removeElement($idAllergen);

        return $this;
    }

    /**
     * @return Collection<int, FoodPreference>
     */
    public function getFoodPreference(): Collection
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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }
}
