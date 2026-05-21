<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: "orders")]
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
        'groups' => ['order:read']
    ],
    denormalizationContext: [
        'groups' => ['order:write']
    ]
)]
class Order
{
    const ORDER_STATUS_PENDING = 'pending';
    const ORDER_STATUS_ACCEPTED = 'accepted';
    const ORDER_STATUS_COMPLETED = 'completed';
    const ORDER_STATUS_REJECTED = 'rejected';
    const ORDER_STATUS_CANCELLED = 'cancelled';

    const PROCESS_STATUS_PENDING = 'pending';
    const PROCESS_STATUS_PROCESSING = 'processing';
    const PROCESS_STATUS_PACKAGING = 'packaging';
    const PROCESS_STATUS_READY_PICKUP = 'ready_for_pickup';
    const PROCESS_STATUS_SHIPPED = 'shipped';
    const PROCESS_STATUS_DELIVERED = 'delivered';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read', 'order:write'])]
    private ?Stock $stock = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['order:read', 'order:write'])]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['order:read', 'order:write'])]
    private float $unitPrice = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['order:read', 'order:write'])]
    private float $totalAmount = 0;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['order:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['order:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'pending'])]
    #[Groups(['order:read', 'order:write'])]
    private string $orderStatus = self::ORDER_STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'pending'])]
    #[Groups(['order:read', 'order:write'])]
    private string $processStatus = self::PROCESS_STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'pickup'])]
    #[Groups(['order:read', 'order:write'])]
    private string $deliveryType = 'pickup';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $deliveryAddress = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?User $customer = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $customerName = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $customerEmail = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $customerPhone = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true, options: ['default' => 'cod'])]
    #[Groups(['order:read', 'order:write'])]
    private ?string $paymentMethod = 'cod';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function getOrderStatus(): string { return $this->orderStatus; }
    public function setOrderStatus(string $orderStatus): static { $this->orderStatus = $orderStatus; $this->updatedAt = new \DateTime(); return $this; }
    public function getProcessStatus(): string { return $this->processStatus; }
    public function setProcessStatus(string $processStatus): static { $this->processStatus = $processStatus; $this->updatedAt = new \DateTime(); return $this; }
    public function getDeliveryType(): string { return $this->deliveryType; }
    public function setDeliveryType(string $deliveryType): static { $this->deliveryType = $deliveryType; return $this; }
    public function getDeliveryAddress(): ?string { return $this->deliveryAddress; }
    public function setDeliveryAddress(?string $deliveryAddress): static { $this->deliveryAddress = $deliveryAddress; return $this; }
    public function getCustomer(): ?User { return $this->customer; }
    public function setCustomer(?User $customer): static { $this->customer = $customer; return $this; }
    public function getCustomerName(): ?string { return $this->customerName; }
    public function setCustomerName(?string $customerName): static { $this->customerName = $customerName; return $this; }
    public function getCustomerEmail(): ?string { return $this->customerEmail; }
    public function setCustomerEmail(?string $customerEmail): static { $this->customerEmail = $customerEmail; return $this; }
    public function getCustomerPhone(): ?string { return $this->customerPhone; }
    public function setCustomerPhone(?string $customerPhone): static { $this->customerPhone = $customerPhone; return $this; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(?string $paymentMethod): static { $this->paymentMethod = $paymentMethod; return $this; }

    // ✅ Custom helper methods for API Platform
    #[Groups(['order:read'])]
    public function getProductName(): ?string
    {
        if ($this->stock && $this->stock->getProduct()) {
            return $this->stock->getProduct()->getName();
        }
        return null;
    }

    #[Groups(['order:read'])]
    public function getProductImage(): ?string
    {
        if ($this->stock && $this->stock->getProduct()) {
            return $this->stock->getProduct()->getImage();
        }
        return null;
    }

    #[Groups(['order:read'])]
    public function getCustomerUsername(): ?string
    {
        return $this->customer ? $this->customer->getUsername() : null;
    }
}