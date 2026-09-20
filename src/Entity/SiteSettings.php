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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contactsHtml = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vkUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notificationEmail = null;

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
    public function getContactsHtml(): ?string { return $this->contactsHtml; }
    public function setContactsHtml(?string $v): static { $this->contactsHtml = $v; return $this; }
    public function getVkUrl(): ?string { return $this->vkUrl; }
    public function setVkUrl(?string $v): static { $this->vkUrl = $v; return $this; }
    public function getTelegramUrl(): ?string { return $this->telegramUrl; }
    public function setTelegramUrl(?string $v): static { $this->telegramUrl = $v; return $this; }
    public function getNotificationEmail(): ?string { return $this->notificationEmail; }
    public function setNotificationEmail(?string $v): static { $this->notificationEmail = $v; return $this; }
    public function getDiscountsHtml(): ?string { return $this->discountsHtml; }
    public function setDiscountsHtml(?string $v): static { $this->discountsHtml = $v; return $this; }
    public function getTentNoteHtml(): ?string { return $this->tentNoteHtml; }
    public function setTentNoteHtml(?string $v): static { $this->tentNoteHtml = $v; return $this; }
    public function getCooperationCtaHtml(): ?string { return $this->cooperationCtaHtml; }
    public function setCooperationCtaHtml(?string $v): static { $this->cooperationCtaHtml = $v; return $this; }
    public function isRegistrationTestMode(): bool { return $this->registrationTestMode; }
    public function setRegistrationTestMode(bool $v): static { $this->registrationTestMode = $v; return $this; }
}
