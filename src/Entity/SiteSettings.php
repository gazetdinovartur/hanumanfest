<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_settings')]
class SiteSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $siteName = 'Хануман Фест';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $tagline = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $footerBackgroundPath = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $companyInfo = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vkUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instagramUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $discountsHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tentNoteHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cooperationCtaHtml = null;

    #[ORM\Column]
    private bool $registrationTestMode = false;

    public function getId(): ?int { return $this->id; }
    public function getSiteName(): string { return $this->siteName; }
    public function setSiteName(string $v): static { $this->siteName = $v; return $this; }
    public function getTagline(): ?string { return $this->tagline; }
    public function setTagline(?string $v): static { $this->tagline = $v; return $this; }
    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $v): static { $this->logoPath = $v; return $this; }
    public function getFooterBackgroundPath(): ?string { return $this->footerBackgroundPath; }
    public function setFooterBackgroundPath(?string $v): static { $this->footerBackgroundPath = $v; return $this; }
    public function getCompanyInfo(): ?string { return $this->companyInfo; }
    public function setCompanyInfo(?string $v): static { $this->companyInfo = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }
    public function getPhone2(): ?string { return $this->phone2; }
    public function setPhone2(?string $v): static { $this->phone2 = $v; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }
    public function getVkUrl(): ?string { return $this->vkUrl; }
    public function setVkUrl(?string $v): static { $this->vkUrl = $v; return $this; }
    public function getTelegramUrl(): ?string { return $this->telegramUrl; }
    public function setTelegramUrl(?string $v): static { $this->telegramUrl = $v; return $this; }
    public function getFacebookUrl(): ?string { return $this->facebookUrl; }
    public function setFacebookUrl(?string $v): static { $this->facebookUrl = $v; return $this; }
    public function getInstagramUrl(): ?string { return $this->instagramUrl; }
    public function setInstagramUrl(?string $v): static { $this->instagramUrl = $v; return $this; }
    public function getDiscountsHtml(): ?string { return $this->discountsHtml; }
    public function setDiscountsHtml(?string $v): static { $this->discountsHtml = $v; return $this; }
    public function getTentNoteHtml(): ?string { return $this->tentNoteHtml; }
    public function setTentNoteHtml(?string $v): static { $this->tentNoteHtml = $v; return $this; }
    public function getCooperationCtaHtml(): ?string { return $this->cooperationCtaHtml; }
    public function setCooperationCtaHtml(?string $v): static { $this->cooperationCtaHtml = $v; return $this; }
    public function isRegistrationTestMode(): bool { return $this->registrationTestMode; }
    public function setRegistrationTestMode(bool $v): static { $this->registrationTestMode = $v; return $this; }
}
