<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pricing_period')]
class PricingPeriod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pricingPeriods')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FestivalSeason $season = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column]
    private \DateTimeImmutable $startAt;

    #[ORM\Column]
    private \DateTimeImmutable $endAt;

    #[ORM\Column]
    private bool $isActive = true;

    /** @var Collection<int, ParticipationPrice> */
    #[ORM\OneToMany(targetEntity: ParticipationPrice::class, mappedBy: 'pricingPeriod', orphanRemoval: true)]
    private Collection $participationPrices;

    public function __construct()
    {
        $this->participationPrices = new ArrayCollection();
        $this->startAt = new \DateTimeImmutable();
        $this->endAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getSeason(): ?FestivalSeason
    {
        return $this->season;
    }

    public function setSeason(?FestivalSeason $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /** @return Collection<int, ParticipationPrice> */
    public function getParticipationPrices(): Collection
    {
        return $this->participationPrices;
    }

    public function addParticipationPrice(ParticipationPrice $participationPrice): static
    {
        if (!$this->participationPrices->contains($participationPrice)) {
            $this->participationPrices->add($participationPrice);
            $participationPrice->setPricingPeriod($this);
        }

        return $this;
    }

    public function removeParticipationPrice(ParticipationPrice $participationPrice): static
    {
        if ($this->participationPrices->removeElement($participationPrice)) {
            if ($participationPrice->getPricingPeriod() === $this) {
                $participationPrice->setPricingPeriod(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
