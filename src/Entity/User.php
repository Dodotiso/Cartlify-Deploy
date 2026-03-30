<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // Add profile picture field
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $profilePicture = null;

    // Add last active field
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastActive = null;

    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getUsername(): ?string 
    { 
        return $this->username; 
    }
    
    public function setUsername(string $username): static 
    { 
        $this->username = $username; 
        return $this; 
    }

    public function getUserIdentifier(): string 
    { 
        return (string) $this->username; 
    }

    public function getRoles(): array
    {
        return array_unique($this->roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string 
    { 
        return $this->password; 
    }
    
    public function setPassword(string $password): static 
    { 
        $this->password = $password; 
        return $this; 
    }

    // Profile picture getter and setter
    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }

    // Last active getter and setter
    public function getLastActive(): ?\DateTimeInterface
    {
        return $this->lastActive;
    }

    public function setLastActive(?\DateTimeInterface $lastActive): static
    {
        $this->lastActive = $lastActive;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void { }

    public function getCreatedAt(): ?\DateTimeImmutable 
    { 
        return $this->createdAt; 
    }
    
    public function setCreatedAt(\DateTimeImmutable $createdAt): static 
    { 
        $this->createdAt = $createdAt; 
        return $this; 
    }
}