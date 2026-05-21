<?php

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: [
        'groups' => ['stock:read']
    ],
    denormalizationContext: [
        'groups' => ['stock:write']
    ]
)]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stock:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['stock:read', 'stock:write'])]
    private ?int $stock = null;

    #[ORM\ManyToOne(inversedBy: 'stocks')]
    #[Groups(['stock:read', 'stock:write'])]
    private ?Product $product = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['stock:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'stock', targetEntity: Order::class)]
    #[Groups(['stock:read'])]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getStock(): ?int { return $this->stock; }
    public function setStock(int $stock): static { $this->stock = $stock; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    #[Groups(['stock:read'])]
    public function getProductName(): ?string
    {
        return $this->product ? $this->product->getName() : null;
    }

    #[Groups(['stock:read'])]
    public function getProductImage(): ?string
    {
        return $this->product ? $this->product->getImage() : null;
    }

    #[Groups(['stock:read'])]
    public function getProductId(): ?int
    {
        return $this->product ? $this->product->getId() : null;
    }

    #[Groups(['stock:read'])]
    public function getProductCategory(): ?string
    {
        if ($this->product && $this->product->getCategory()) {
            return $this->product->getCategory()->getCategory();
        }
        return null;
    }

    #[Groups(['stock:read'])]
    public function getProductPrice(): ?float
    {
        return $this->product ? $this->product->getPrice() : null;
    }

    #[Groups(['stock:read'])]
    public function getProductDescription(): ?string
    {
        return $this->product ? $this->product->getDescription() : null;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }
}