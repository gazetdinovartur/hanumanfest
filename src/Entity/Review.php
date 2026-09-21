<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
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

    /** @var Collection<int, ReviewMedia> */
    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $media;

    public function __construct()
    {
        $this->media = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $v): static
    {
        $this->authorName = $v;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $v): static
    {
        $this->body = $v;

        return $this;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $v): static
    {
        $this->photoPath = $v;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $v): static
    {
        $this->sortOrder = $v;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $v): static
    {
        $this->published = $v;

        return $this;
    }

    /** @return Collection<int, ReviewMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    /** @return list<ReviewMedia> */
    public function getPublishedMedia(): array
    {
        $items = [];
        foreach ($this->media as $item) {
            if ($item->isPublished()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function addMedia(ReviewMedia $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setReview($this);
        }

        return $this;
    }

    public function removeMedia(ReviewMedia $media): static
    {
        if ($this->media->removeElement($media) && $media->getReview() === $this) {
            $media->setReview(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->authorName !== '' ? $this->authorName : 'Review#'.($this->id ?? 0);
    }
}
