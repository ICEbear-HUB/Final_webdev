<?php

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
class StockMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stockMovements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 20)]
    private ?string $movementType = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column]
    private ?int $previousStock = null;

    #[ORM\Column]
    private ?int $newStock = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'stockMovements')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getMovementType(): ?string
    {
        return $this->movementType;
    }

    public function setMovementType(string $movementType): static
    {
        $this->movementType = $movementType;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPreviousStock(): ?int
    {
        return $this->previousStock;
    }

    public function setPreviousStock(int $previousStock): static
    {
        $this->previousStock = $previousStock;

        return $this;
    }

    public function getNewStock(): ?int
    {
        return $this->newStock;
    }

    public function setNewStock(int $newStock): static
    {
        $this->newStock = $newStock;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPerformedBy(): ?User
    {
        return $this->performedBy;
    }

    public function setPerformedBy(?User $performedBy): static
    {
        $this->performedBy = $performedBy;

        return $this;
    }
}
