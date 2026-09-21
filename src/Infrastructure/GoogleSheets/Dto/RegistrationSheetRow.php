<?php

namespace App\Infrastructure\GoogleSheets\Dto;

/**
 * Одна строка листа «Регистрации».
 * Строка 1 — английские ключи (по ним пишет код).
 * Строка 2 — русские подписи.
 * Дальше — заявки. Порядок колонок = HEADERS.
 *
 * paidTotal — сумма успешных платежей по заявке.
 * remaining — сколько осталось доплатить (totalAmount − paidTotal).
 * payments — все успешные платежи в одной ячейке.
 */
readonly class RegistrationSheetRow
{
    /** @var list<string> */
    public const HEADERS = [
        'name',
        'phone',
        'participationOptionName',
        'adultsCount',
        'childrenCount',
        'paidTotal',
        'remaining',
        'transferIncluded',
        'notes',
        'totalAmount',
        'pricingPeriodName',
        'payments',
        'email',
        'applicationUuid',
    ];

    /** Подписи для второй строки. Порядок совпадает с HEADERS. */
    public const RUSSIAN_HEADERS = [
        'ФИО',
        'Телефон',
        'Вариант участия',
        'Взрослых',
        'Детей',
        'Оплачено',
        'Осталось оплатить',
        'Трансфер',
        'Заметка',
        'Стоимость',
        'Ценовой период',
        'Платежи',
        'Почта',
        'Id заявки',
    ];

    /** @var list<string> */
    public const APPLICATION_FIELDS = [
        'name',
        'phone',
        'participationOptionName',
        'adultsCount',
        'childrenCount',
        'totalAmount',
        'paidTotal',
        'transferIncluded',
        'notes',
        'remaining',
        'pricingPeriodName',
        'payments',
        'email',
        'applicationUuid',
    ];

    /** @var list<string> */
    public const PAYMENT_FIELDS = [
        'payments',
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
        public string $participationOptionName,
        public string $transferIncluded,
        public string $notes,
        public string $payments,
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
            'participationOptionName' => $this->participationOptionName,
            'transferIncluded' => $this->transferIncluded,
            'notes' => $this->notes,
            'payments' => $this->payments,
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

        return $name === 'ФИО' && ($email === 'Почта' || $email === '');
    }
}
