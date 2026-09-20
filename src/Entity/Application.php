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

    #[ORM\ManyToOne(inversedBy: 'applications')]
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
        if ($this->user === $user) {
            return $this;
        }

        $previous = $this->user;
        $this->user = $user;
        $previous?->getApplications()->removeElement($this);
        if ($user !== null && !$user->getApplications()->contains($this)) {
            $user->getApplications()->add($this);
        }

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

    public function getAmountDueNow(): int
    {
        $remaining = $this->getRemainingAmount();
        if ($remaining <= 0) {
            return 0;
        }

        if ($this->paidAmount > 0) {
            return $remaining;
        }

        $payNow = (int) ($this->payload['payNowAmount'] ?? 0);
        if ($payNow <= 0) {
            return $remaining;
        }

        return min($payNow, $remaining);
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

    public function getParticipationOptionName(): string
    {
        return trim((string) ($this->payload['participationOptionName'] ?? ''));
    }

    public function getAdultsCount(): int
    {
        return max(1, (int) ($this->payload['adultsCount'] ?? 1));
    }

    public function getChildrenCount(): int
    {
        return max(0, (int) ($this->payload['childrenCount'] ?? 0));
    }

    public function isTransferIncluded(): bool
    {
        return !empty($this->payload['transferIncluded']);
    }

    public function getPaymentFactorLabel(): string
    {
        $factor = (float) ($this->payload['paymentFactor'] ?? 1);

        return $factor < 1 ? 'Предоплата 50%' : 'Полная оплата';
    }

    public function getTentRoommate(): string
    {
        return trim((string) ($this->payload['tentRoommate'] ?? ''));
    }

    public function getPayNowAmount(): int
    {
        return (int) ($this->payload['payNowAmount'] ?? $this->getAmountDueNow());
    }

    /**
     * Человекочитаемые строки заявки для админки.
     *
     * @return list<array{label: string, value: string}>
     */
    public function getReadableDetails(): array
    {
        $user = $this->user;
        $periodName = trim((string) ($this->payload['pricingPeriodName'] ?? $this->pricingPeriod?->getName() ?? ''));
        $rows = [
            ['label' => 'Имя', 'value' => $user?->getName() ?? ''],
            ['label' => 'Email', 'value' => $user?->getEmail() ?? ''],
            ['label' => 'Телефон', 'value' => $user?->getPhone() ?? ''],
            ['label' => 'Статус', 'value' => $this->status->label()],
            ['label' => 'Вариант участия', 'value' => $this->getParticipationOptionName()],
            ['label' => 'Ценовой период', 'value' => $periodName],
            ['label' => 'Взрослых', 'value' => (string) $this->getAdultsCount()],
            ['label' => 'Детей до 16 лет', 'value' => (string) $this->getChildrenCount()],
            ['label' => 'Трансфер', 'value' => $this->isTransferIncluded() ? 'Да' : 'Нет'],
            ['label' => 'Вариант оплаты', 'value' => $this->getPaymentFactorLabel()],
            ['label' => 'С кем в палатке', 'value' => $this->getTentRoommate()],
            ['label' => 'Итого', 'value' => self::formatMoney($this->totalAmount)],
            ['label' => 'Оплачено', 'value' => self::formatMoney($this->paidAmount)],
            ['label' => 'Осталось', 'value' => self::formatMoney($this->getRemainingAmount())],
            ['label' => 'К оплате сейчас', 'value' => self::formatMoney($this->getPayNowAmount())],
            ['label' => 'Создана', 'value' => $this->createdAt?->format('d.m.Y H:i') ?? ''],
        ];

        if ($this->isTest) {
            array_splice($rows, 4, 0, [['label' => 'Тест', 'value' => 'Да']]);
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['value'] !== '',
        ));
    }

    private static function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' ₽';
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
        $name = trim((string) ($this->user?->getName() ?? ''));
        $id = $this->id ?? 0;
        if ($name !== '') {
            return $id > 0 ? sprintf('#%d %s', $id, $name) : $name;
        }

        return $id > 0 ? sprintf('#%d', $id) : 'Заявка';
    }
}
