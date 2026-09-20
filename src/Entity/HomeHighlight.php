<?php

namespace App\Entity;

use App\Enum\HomeHighlightColumn;
use App\Enum\HomeHighlightStyle;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'home_highlight')]
class HomeHighlight
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $text = '';

    #[ORM\Column(length: 16, enumType: HomeHighlightColumn::class)]
    private HomeHighlightColumn $columnSide = HomeHighlightColumn::Left;

    #[ORM\Column(length: 16, enumType: HomeHighlightStyle::class)]
    private HomeHighlightStyle $style = HomeHighlightStyle::Normal;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $published = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getColumnSide(): HomeHighlightColumn
    {
        return $this->columnSide;
    }

    public function setColumnSide(HomeHighlightColumn $columnSide): static
    {
        $this->columnSide = $columnSide;

        return $this;
    }

    public function getStyle(): HomeHighlightStyle
    {
        return $this->style;
    }

    public function setStyle(HomeHighlightStyle $style): static
    {
        $this->style = $style;

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

    public function __toString(): string
    {
        $preview = mb_substr($this->text, 0, 40);

        return $preview !== '' ? $preview : 'Плитка#'.($this->id ?? 0);
    }
}
