<?php

namespace App\Entity;

use App\Enum\SitePageTemplate;
use App\Repository\SitePageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SitePageRepository::class)]
#[ORM\Table(name: 'site_page')]
#[ORM\UniqueConstraint(name: 'uniq_site_page_slug', columns: ['slug'])]
class SitePage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(type: 'text')]
    private string $contentHtml = '';

    #[ORM\Column(length: 32, enumType: SitePageTemplate::class)]
    private SitePageTemplate $template = SitePageTemplate::Default;

    #[ORM\Column]
    private bool $showInFooter = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $published = true;

    #[ORM\Column(name: 'kitchen_video_1', length: 500, nullable: true)]
    private ?string $kitchenVideo1 = null;

    #[ORM\Column(name: 'kitchen_video_2', length: 500, nullable: true)]
    private ?string $kitchenVideo2 = null;

    #[ORM\Column(name: 'kitchen_video_3', length: 500, nullable: true)]
    private ?string $kitchenVideo3 = null;

    #[ORM\Column(name: 'kitchen_video_4', length: 500, nullable: true)]
    private ?string $kitchenVideo4 = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug, '/');

        return $this;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(string $contentHtml): static
    {
        $this->contentHtml = $contentHtml;

        return $this;
    }

    public function getTemplate(): SitePageTemplate
    {
        return $this->template;
    }

    public function setTemplate(SitePageTemplate $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function isShowInFooter(): bool
    {
        return $this->showInFooter;
    }

    public function setShowInFooter(bool $showInFooter): static
    {
        $this->showInFooter = $showInFooter;

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

    public function getKitchenVideo1(): ?string
    {
        return $this->kitchenVideo1;
    }

    public function setKitchenVideo1(?string $kitchenVideo1): static
    {
        $this->kitchenVideo1 = $kitchenVideo1;

        return $this;
    }

    public function getKitchenVideo2(): ?string
    {
        return $this->kitchenVideo2;
    }

    public function setKitchenVideo2(?string $kitchenVideo2): static
    {
        $this->kitchenVideo2 = $kitchenVideo2;

        return $this;
    }

    public function getKitchenVideo3(): ?string
    {
        return $this->kitchenVideo3;
    }

    public function setKitchenVideo3(?string $kitchenVideo3): static
    {
        $this->kitchenVideo3 = $kitchenVideo3;

        return $this;
    }

    public function getKitchenVideo4(): ?string
    {
        return $this->kitchenVideo4;
    }

    public function setKitchenVideo4(?string $kitchenVideo4): static
    {
        $this->kitchenVideo4 = $kitchenVideo4;

        return $this;
    }

    /** @return list<string> */
    public function getKitchenVideos(): array
    {
        return array_values(array_filter([
            $this->kitchenVideo1,
            $this->kitchenVideo2,
            $this->kitchenVideo3,
            $this->kitchenVideo4,
        ], static fn (?string $path): bool => $path !== null && $path !== ''));
    }

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Страница#'.($this->id ?? 0);
    }
}
