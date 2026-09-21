<?php

namespace App\Entity;

use App\Enum\ReviewMediaKind;
use App\Repository\ReviewMediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewMediaRepository::class)]
#[ORM\Table(name: 'review_media')]
class ReviewMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Review $review = null;

    #[ORM\Column(length: 16, enumType: ReviewMediaKind::class)]
    private ReviewMediaKind $kind = ReviewMediaKind::Image;

    #[ORM\Column(length: 500)]
    private string $path = '';

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $published = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): static
    {
        $this->review = $review;

        return $this;
    }

    public function getKind(): ReviewMediaKind
    {
        return $this->kind;
    }

    public function setKind(ReviewMediaKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function isImage(): bool
    {
        return $this->kind === ReviewMediaKind::Image;
    }

    public function isVideo(): bool
    {
        return $this->kind === ReviewMediaKind::Video;
    }
}
