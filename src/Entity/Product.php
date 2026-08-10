<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column]
    private bool $isActive = true;

    /** @var Collection<int, PricingPeriod> */
    #[ORM\OneToMany(targetEntity: PricingPeriod::class, mappedBy: 'product', orphanRemoval: true)]
    private Collection $pricingPeriods;

    /** @var Collection<int, ParticipationOption> */
    #[ORM\OneToMany(targetEntity: ParticipationOption::class, mappedBy: 'product', orphanRemoval: true)]
    private Collection $participationOptions;

    public function __construct()
    {
        $this->pricingPeriods = new ArrayCollection();
        $this->participationOptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    /** @return Collection<int, PricingPeriod> */
    public function getPricingPeriods(): Collection
    {
        return $this->pricingPeriods;
    }

    public function addPricingPeriod(PricingPeriod $pricingPeriod): static
    {
        if (!$this->pricingPeriods->contains($pricingPeriod)) {
            $this->pricingPeriods->add($pricingPeriod);
            $pricingPeriod->setProduct($this);
        }

        return $this;
    }

    public function removePricingPeriod(PricingPeriod $pricingPeriod): static
    {
        if ($this->pricingPeriods->removeElement($pricingPeriod)) {
            if ($pricingPeriod->getProduct() === $this) {
                $pricingPeriod->setProduct(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, ParticipationOption> */
    public function getParticipationOptions(): Collection
    {
        return $this->participationOptions;
    }

    public function addParticipationOption(ParticipationOption $participationOption): static
    {
        if (!$this->participationOptions->contains($participationOption)) {
            $this->participationOptions->add($participationOption);
            $participationOption->setProduct($this);
        }

        return $this;
    }

    public function removeParticipationOption(ParticipationOption $participationOption): static
    {
        if ($this->participationOptions->removeElement($participationOption)) {
            if ($participationOption->getProduct() === $this) {
                $participationOption->setProduct(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
