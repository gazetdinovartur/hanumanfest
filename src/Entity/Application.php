<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'application')]
#[ORM\HasLifecycleCallbacks]
class Application
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?PricingPeriod $pricingPeriod = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FestivalSeason $season = null;

    #[ORM\Column(enumType: ApplicationStatus::class)]
    private ApplicationStatus $status = ApplicationStatus::New;

    #[ORM\Column]
    private int $totalAmount = 0;

    #[ORM\Column]
    private int $paidAmount = 0;

    #[ORM\Column]
    private bool $isTest = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'application', orphanRemoval: true)]
    private Collection $payments;

    /** @var Collection<int, PaymentLink> */
    #[ORM\OneToMany(targetEntity: PaymentLink::class, mappedBy: 'application', orphanRemoval: true)]
    private Collection $paymentLinks;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
        $this->payments = new ArrayCollection();
        $this->paymentLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getPricingPeriod(): ?PricingPeriod
    {
        return $this->pricingPeriod;
    }

    public function setPricingPeriod(?PricingPeriod $pricingPeriod): static
    {
        $this->pricingPeriod = $pricingPeriod;

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

    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(ApplicationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(int $paidAmount): static
    {
        $this->paidAmount = $paidAmount;

        return $this;
    }

    public function getRemainingAmount(): int
    {
        return max(0, $this->totalAmount - $this->paidAmount);
    }

    public function isTest(): bool
    {
        return $this->isTest;
    }

    public function setIsTest(bool $isTest): static
    {
        $this->isTest = $isTest;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setApplication($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getApplication() === $this) {
                $payment->setApplication(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, PaymentLink> */
    public function getPaymentLinks(): Collection
    {
        return $this->paymentLinks;
    }

    public function addPaymentLink(PaymentLink $paymentLink): static
    {
        if (!$this->paymentLinks->contains($paymentLink)) {
            $this->paymentLinks->add($paymentLink);
            $paymentLink->setApplication($this);
        }

        return $this;
    }

    public function removePaymentLink(PaymentLink $paymentLink): static
    {
        if ($this->paymentLinks->removeElement($paymentLink)) {
            if ($paymentLink->getApplication() === $this) {
                $paymentLink->setApplication(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->uuid;
    }
}
