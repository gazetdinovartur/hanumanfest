<?php

namespace App\Entity;

use App\Repository\GalleryItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryItemRepository::class)]
#[ORM\Table(name: 'gallery_item')]
class GalleryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private string $imagePath = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $published = true;

    public function getId(): ?int { return $this->id; }
    public function getImagePath(): string { return $this->imagePath; }
    public function setImagePath(string $v): static { $this->imagePath = $v; return $this; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $v): static { $this->caption = $v; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): static { $this->sortOrder = $v; return $this; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): static { $this->published = $v; return $this; }
}
