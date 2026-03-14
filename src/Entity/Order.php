<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: "orders")]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stock $stock = null;

    #[ORM\Column(type: 'integer')]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $unitPrice = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $totalAmount = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getStock(): ?Stock { return $this->stock; }
    public function setStock(?Stock $stock): static { $this->stock = $stock; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }

    public function getUnitPrice(): float { return $this->unitPrice; }
    public function setUnitPrice(float $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getTotalAmount(): float { return $this->totalAmount; }
    public function setTotalAmount(float $totalAmount): static { $this->totalAmount = $totalAmount; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
