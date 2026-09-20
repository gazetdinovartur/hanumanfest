<?php

namespace App\Infrastructure\GoogleSheets\Dto;

/**
 * Одна строка листа «Регистрации».
 * Строка 1 — английские ключи (по ним пишет код).
 * Строка 2 — русские подписи.
 * Дальше — заявки. Порядок колонок при создании пустого листа = HEADERS.
 *
 * paidTotal — сумма успешных платежей по заявке.
 * remaining — сколько осталось доплатить (totalAmount − paidTotal).
 */
readonly class RegistrationSheetRow
{
    /** @var list<string> */
    public const HEADERS = [
        'name',
        'phone',
        'email',
        'adultsCount',
        'childrenCount',
        'paidTotal',
        'remaining',
        'participationOptionName',
        'transferIncluded',
        'notes',
        'applicationUuid',
        'payment1Amount',
        'payment1Date',
        'payment1Id',
        'payment2Amount',
        'payment2Date',
        'payment2Id',
        'payNowAmount',
        'totalAmount',
        'paymentFactor',
        'pricingPeriodName',
    ];

    /** Подписи для второй строки. Порядок совпадает с HEADERS. */
    public const RUSSIAN_HEADERS = [
        'ФИО',
        'Телефон',
        'Почта',
        'Взрослых',
        'Детей',
        'Оплачено',
        'Осталось оплатить',
        'Вариант участия',
        'Трансфер',
        'Заметка',
        'Id заявки',
        'Платёж 1, сумма',
        'Платёж 1, дата',
        'Платёж 1, id',
        'Платёж 2, сумма',
        'Платёж 2, дата',
        'Платёж 2, id',
        'К оплате сейчас',
        'Стоимость',
        'Доля оплаты',
        'Ценовой период',
    ];

    /** @var list<string> */
    public const APPLICATION_FIELDS = [
        'name',
        'phone',
        'email',
        'adultsCount',
        'childrenCount',
        'totalAmount',
        'paidTotal',
        'participationOptionName',
        'transferIncluded',
        'paymentFactor',
        'notes',
        'remaining',
        'payNowAmount',
        'pricingPeriodName',
        'applicationUuid',
    ];

    /** @var list<string> */
    public const PAYMENT_FIELDS = [
        'payment1Amount',
        'payment1Date',
        'payment1Id',
        'payment2Amount',
        'payment2Date',
        'payment2Id',
        'paidTotal',
        'remaining',
        'applicationUuid',
    ];

    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $adultsCount,
        public string $childrenCount,
        public string $totalAmount,
        public string $payNowAmount,
        public string $participationOptionName,
        public string $transferIncluded,
        public string $paymentFactor,
        public string $notes,
        public string $payment1Amount,
        public string $payment1Date,
        public string $payment1Id,
        public string $payment2Amount,
        public string $payment2Date,
        public string $payment2Id,
        public string $paidTotal,
        public string $remaining,
        public string $pricingPeriodName,
        public string $applicationUuid,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toAssoc(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'adultsCount' => $this->adultsCount,
            'childrenCount' => $this->childrenCount,
            'totalAmount' => $this->totalAmount,
            'payNowAmount' => $this->payNowAmount,
            'participationOptionName' => $this->participationOptionName,
            'transferIncluded' => $this->transferIncluded,
            'paymentFactor' => $this->paymentFactor,
            'notes' => $this->notes,
            'payment1Amount' => $this->payment1Amount,
            'payment1Date' => $this->payment1Date,
            'payment1Id' => $this->payment1Id,
            'payment2Amount' => $this->payment2Amount,
            'payment2Date' => $this->payment2Date,
            'payment2Id' => $this->payment2Id,
            'paidTotal' => $this->paidTotal,
            'remaining' => $this->remaining,
            'pricingPeriodName' => $this->pricingPeriodName,
            'applicationUuid' => $this->applicationUuid,
        ];
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    public function valuesFor(array $headers): array
    {
        $assoc = $this->toAssoc();
        $row = [];
        foreach ($headers as $header) {
            $row[] = $assoc[$header] ?? '';
        }

        return $row;
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    public static function russianLabelsFor(array $headers): array
    {
        $map = [];
        foreach (self::HEADERS as $i => $key) {
            $map[strtolower($key)] = self::RUSSIAN_HEADERS[$i];
        }
        $row = [];
        foreach ($headers as $header) {
            $row[] = $map[strtolower(trim($header))] ?? '';
        }

        return $row;
    }

    /**
     * @param array<string, string> $row
     */
    public static function isRussianLabelRow(array $row): bool
    {
        $name = trim($row['name'] ?? '');
        $email = trim($row['email'] ?? '');

        return $name === self::RUSSIAN_HEADERS[0] && $email === self::RUSSIAN_HEADERS[2];
    }
}
