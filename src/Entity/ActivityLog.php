<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: "activity_log")]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['activity_log:read']]
)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['activity_log:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['activity_log:read'])]
    private ?int $userId = null;

    #[ORM\Column(length: 180)]
    #[Groups(['activity_log:read'])]
    private string $username = 'anonymous';

    #[ORM\Column(length: 60)]
    #[Groups(['activity_log:read'])]
    private string $role = 'ANONYMOUS';

    #[ORM\Column(length: 50)]
    #[Groups(['activity_log:read'])]
    private string $action;

    #[ORM\Column(type: 'text')]
    #[Groups(['activity_log:read'])]
    private string $target;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['activity_log:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $userId): static { $this->userId = $userId; return $this; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getTarget(): string { return $this->target; }
    public function setTarget(string $target): static { $this->target = $target; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
}