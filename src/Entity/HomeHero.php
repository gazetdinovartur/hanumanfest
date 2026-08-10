<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'home_hero')]
class HomeHero
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eventDates = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleMain = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $titleSecondary = null;

    #[ORM\Column(length: 255)]
    private string $headline = 'Хануман Фест';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aboutHtml = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $promoVideoLeft = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $promoVideoRight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ctaLabel = 'Участвовать';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ctaUrl = '#register';

    public function getId(): ?int { return $this->id; }
    public function getEventDates(): ?string { return $this->eventDates; }
    public function setEventDates(?string $v): static { $this->eventDates = $v; return $this; }
    public function getTitleMain(): ?string { return $this->titleMain; }
    public function setTitleMain(?string $v): static { $this->titleMain = $v; return $this; }
    public function getTitleSecondary(): ?string { return $this->titleSecondary; }
    public function setTitleSecondary(?string $v): static { $this->titleSecondary = $v; return $this; }
    public function getHeadline(): string { return $this->headline; }
    public function setHeadline(string $v): static { $this->headline = $v; return $this; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $v): static { $this->imagePath = $v; return $this; }
    public function getAboutHtml(): ?string { return $this->aboutHtml; }
    public function setAboutHtml(?string $v): static { $this->aboutHtml = $v; return $this; }
    public function getPromoVideoLeft(): ?string { return $this->promoVideoLeft; }
    public function setPromoVideoLeft(?string $v): static { $this->promoVideoLeft = $v; return $this; }
    public function getPromoVideoRight(): ?string { return $this->promoVideoRight; }
    public function setPromoVideoRight(?string $v): static { $this->promoVideoRight = $v; return $this; }
    public function getCtaLabel(): ?string { return $this->ctaLabel; }
    public function setCtaLabel(?string $v): static { $this->ctaLabel = $v; return $this; }
    public function getCtaUrl(): ?string { return $this->ctaUrl; }
    public function setCtaUrl(?string $v): static { $this->ctaUrl = $v; return $this; }
}
