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
    #[Groups(['user:read', 'address:read', 'allergen:read', 'food_preference:read', 'conversation:read', 'offer:read', 'message:read', 'transaction:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read', 'allergen:read', 'food_preference:read'])]
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
    #[Groups(['user:read', 'admin:read'])]
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
    #[Groups(['user:read', 'user:write', 'user:profile', 'offer:read', 'transaction:read'])]
    #[Assert\NotBlank(message: "Le nom ne peut pas être vide")]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: "Le nom doit contenir au moins {{ limit }} caractères",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $last_name = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Groups(['user:read', 'user:write', 'user:profile', 'offer:read', 'transaction:read'])]
    #[Assert\NotBlank(message: "Le prénom ne peut pas être vide")]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: "Le prénom doit contenir au moins {{ limit }} caractères",
        maxMessage: "Le prénom ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $first_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $githubId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeAccountId = null;

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

    /**
     * @var Collection<int, Offer>
     */
    #[ORM\OneToMany(targetEntity: Offer::class, mappedBy: 'seller')]
    private Collection $offers;

    /**
     * @var Collection<int, Offer>
     */
    #[ORM\OneToMany(targetEntity: Offer::class, mappedBy: 'buyer')]
    private Collection $orders;

    /**
     * @var Collection<int, Rating>
     */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'rater_user_id')]
    private Collection $ratings;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, SavedSearchFilters>
     */
    #[ORM\OneToMany(targetEntity: SavedSearchFilters::class, mappedBy: 'user')]
    private Collection $savedSearchFilters;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'buyer')]
    private Collection $conversations;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(mappedBy: 'reviewer', targetEntity: Review::class)]
    private Collection $reviewsGiven;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(mappedBy: 'reviewed', targetEntity: Review::class)]
    #[Groups(['user:read', 'user:profile'])]
    private Collection $reviewsReceived;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read', 'user:profile', 'offer:read'])]
    private ?float $averageRating = null;

    public function __construct()
    {
        $this->address = new ArrayCollection();
        $this->allergen = new ArrayCollection();
        $this->food_preferences = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->savedSearchFilters = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->reviewsGiven = new ArrayCollection();
        $this->reviewsReceived = new ArrayCollection();
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

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): static
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getFullName(): string
    {
        $lastNameInitial = $this->last_name ? strtoupper($this->last_name[0]) . '.' : '';
        return trim(sprintf('%s %s', $this->first_name ?? '', $lastNameInitial));
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

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): self
    {
        $this->stripeAccountId = $stripeAccountId;
        
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
            $offer->setSeller($this);
        }

        return $this;
    }

    public function removeOffer(Offer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            // set the owning side to null (unless already changed)
            if ($offer->getSeller() === $this) {
                $offer->setSeller(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Offer>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Offer $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setBuyer($this);
        }

        return $this;
    }

    public function removeOrder(Offer $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getBuyer() === $this) {
                $order->setBuyer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(Rating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setRaterUserId($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getRaterUserId() === $this) {
                $rating->setRaterUserId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function getSavedSearchFilters(): Collection
    {
        return $this->savedSearchFilters;
    }

    public function addSavedSearchFilter(SavedSearchFilters $savedSearchFilter): static
    {
        if (!$this->savedSearchFilters->contains($savedSearchFilter)) {
            $this->savedSearchFilters->add($savedSearchFilter);
            $savedSearchFilter->setUser($this);
        }

        return $this;
    }

    public function removeSavedSearchFilter(SavedSearchFilters $savedSearchFilter): static
    {
        if ($this->savedSearchFilters->removeElement($savedSearchFilter)) {
            // set the owning side to null (unless already changed)
            if ($savedSearchFilter->getUser() === $this) {
                $savedSearchFilter->setUser(null);
            }
        }

        return $this;
    }

    #[Groups(['user:read'])]
    public function getSavedSearchCount(): int
    {
        return $this->savedSearchFilters->count();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setBuyer($this);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        if ($this->conversations->removeElement($conversation)) {
            // set the owning side to null (unless already changed)
            if ($conversation->getBuyer() === $this) {
                $conversation->setBuyer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviewsGiven(): Collection
    {
        return $this->reviewsGiven;
    }

    public function addReviewGiven(Review $review): static
    {
        if (!$this->reviewsGiven->contains($review)) {
            $this->reviewsGiven->add($review);
            $review->setReviewer($this);
        }

        return $this;
    }

    public function removeReviewGiven(Review $review): static
    {
        if ($this->reviewsGiven->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getReviewer() === $this) {
                $review->setReviewer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviewsReceived(): Collection
    {
        return $this->reviewsReceived;
    }

    public function addReviewReceived(Review $review): static
    {
        if (!$this->reviewsReceived->contains($review)) {
            $this->reviewsReceived->add($review);
            $review->setReviewed($this);
        }

        return $this;
    }

    public function removeReviewReceived(Review $review): static
    {
        if ($this->reviewsReceived->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getReviewed() === $this) {
                $review->setReviewed(null);
            }
        }

        return $this;
    }
    
    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    public function setAverageRating(?float $averageRating): static
    {
        $this->averageRating = $averageRating;

        return $this;
    }
    
    /**
     * @return Collection<int, Review>
     */
    public function getApprovedReviews(): Collection
    {
        $approved = $this->reviewsReceived->filter(function ($review) {
            return $review->getStatus() === \App\Enums\ReviewStatus::APPROVED;
        });

        $iterator = $approved instanceof ArrayCollection
            ? $approved->getIterator()
            : new \ArrayIterator($approved->toArray());

        $iterator = iterator_to_array($iterator);
        usort($iterator, function ($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        return new ArrayCollection($iterator);
    }
}
