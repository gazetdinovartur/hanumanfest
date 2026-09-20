<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\UniqueConstraint(name: 'uniq_provider_payment', columns: ['provider', 'provider_payment_id'])]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Application $application = null;

    #[ORM\Column(enumType: PaymentProvider::class)]
    private PaymentProvider $provider = PaymentProvider::Yookassa;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerPaymentId = null;

    #[ORM\Column]
    private int $amount = 0;

    #[ORM\Column]
    private int $refundedAmount = 0;

    #[ORM\Column(enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): static
    {
        $this->application = $application;

        return $this;
    }

    public function getProvider(): PaymentProvider
    {
        return $this->provider;
    }

    public function setProvider(PaymentProvider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function setProviderPaymentId(?string $providerPaymentId): static
    {
        $this->providerPaymentId = $providerPaymentId;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getRefundedAmount(): int
    {
        return $this->refundedAmount;
    }

    public function setRefundedAmount(int $refundedAmount): static
    {
        $this->refundedAmount = max(0, $refundedAmount);

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('#%d — %d ₽', $this->id ?? 0, $this->amount);
    }
}
