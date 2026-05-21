<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['user:read']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read'])]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $profilePicture = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $lastActive = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Order::class)]
    private Collection $orders;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $verificationToken = null;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getUserIdentifier(): string { return (string) $this->username; }
    public function getRoles(): array { return array_unique($this->roles); }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): static { $this->password = $password; return $this; }
    public function getProfilePicture(): ?string { return $this->profilePicture; }
    public function setProfilePicture(?string $profilePicture): static { $this->profilePicture = $profilePicture; return $this; }
    public function getLastActive(): ?\DateTimeInterface { return $this->lastActive; }
    public function setLastActive(?\DateTimeInterface $lastActive): static { $this->lastActive = $lastActive; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getOrders(): Collection { return $this->orders; }
    public function getGoogleId(): ?string { return $this->googleId; }
    public function setGoogleId(?string $googleId): static { $this->googleId = $googleId; return $this; }
    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }
    public function getVerificationToken(): ?string { return $this->verificationToken; }
    public function setVerificationToken(?string $verificationToken): static { $this->verificationToken = $verificationToken; return $this; }

    // ============================================
    // API SERIALIZATION
    // ============================================
    #[Groups(['user:read'])]
    public function getRole(): string
    {
        return match (true) {
            in_array('ROLE_ADMIN', $this->roles) => 'Admin',
            in_array('ROLE_STAFF', $this->roles) => 'Staff',
            default => 'Customer',
        };
    }

    #[Groups(['user:read'])]
    public function getTotalOrders(): int
    {
        return $this->orders->count();
    }

    #[Groups(['user:read'])]
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    #[Groups(['user:read'])]
    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => $this->password ? hash('crc32c', $this->password) : null,
        ];
    }

    public function eraseCredentials(): void {}
}