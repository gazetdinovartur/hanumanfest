<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'review')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $authorName = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photoPath = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $published = true;

    public function getId(): ?int { return $this->id; }
    public function getAuthorName(): string { return $this->authorName; }
    public function setAuthorName(string $v): static { $this->authorName = $v; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $v): static { $this->body = $v; return $this; }
    public function getPhotoPath(): ?string { return $this->photoPath; }
    public function setPhotoPath(?string $v): static { $this->photoPath = $v; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): static { $this->sortOrder = $v; return $this; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): static { $this->published = $v; return $this; }
    public function __toString(): string { return $this->authorName !== '' ? $this->authorName : 'Review#'.($this->id ?? 0); }
}
