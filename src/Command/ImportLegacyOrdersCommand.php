<?php

namespace App\Command;

use App\Entity\Application;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\Payment;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Repository\ApplicationRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Util\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:import:legacy-orders',
    description: 'Import legacy applications/payments from CSV exports (Forminator + Google Sheet)',
)]
class ImportLegacyOrdersCommand extends Command
{
    /** @var array<string, User> */
    private array $userByEmailCache = [];

    /** @var array<string, Application> */
    private array $applicationByProductEmailCache = [];

    /** @var array<string, Payment> */
    private array $paymentByProviderIdCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApplicationRepository $applicationRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Single CSV source (generic mode)')
            ->addOption('sheet-source', null, InputOption::VALUE_OPTIONAL, 'Google Sheet CSV export path/URL')
            ->addOption('forminator-source', null, InputOption::VALUE_OPTIONAL, 'Forminator CSV export path/URL')
            ->addOption('product-slug', null, InputOption::VALUE_OPTIONAL, 'Product slug for imported rows', 'hanuman-fest')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and preview import without writing to DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->userByEmailCache = [];
        $this->applicationByProductEmailCache = [];
        $this->paymentByProviderIdCache = [];

        $sources = [];
        $singleSource = (string) $input->getOption('source');
        $sheetSource = (string) $input->getOption('sheet-source');
        $forminatorSource = (string) $input->getOption('forminator-source');

        if ($singleSource !== '') {
            $sources[] = ['path' => $singleSource, 'hint' => 'single'];
        }
        if ($sheetSource !== '') {
            $sources[] = ['path' => $sheetSource, 'hint' => 'sheet'];
        }
        if ($forminatorSource !== '') {
            $sources[] = ['path' => $forminatorSource, 'hint' => 'forminator'];
        }

        if ($sources === []) {
            $io->error('Pass --source=<csv> or --sheet-source=<csv> and/or --forminator-source=<csv>.');

            return Command::FAILURE;
        }

        $productSlug = (string) $input->getOption('product-slug');
        $dryRun = (bool) $input->getOption('dry-run');

        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['slug' => $productSlug]);
        if (!$product) {
            $io->error(sprintf('Product "%s" not found.', $productSlug));

            return Command::FAILURE;
        }

        /** @var array<string, ParticipationOption> $optionsByCode */
        $optionsByCode = [];
        /** @var array<string, ParticipationOption> $optionsByName */
        $optionsByName = [];
        $options = $this->entityManager->getRepository(ParticipationOption::class)->findBy(['product' => $product]);
        foreach ($options as $option) {
            $optionsByCode[$option->getCode()] = $option;
            $optionsByName[$this->normalizeText($option->getName())] = $option;
        }

        /** @var array<string, PricingPeriod> $periodsByName */
        $periodsByName = [];
        /** @var list<PricingPeriod> $periods */
        $periods = $this->entityManager->getRepository(PricingPeriod::class)->findBy(
            ['product' => $product],
            ['startAt' => 'ASC']
        );
        foreach ($periods as $period) {
            $periodsByName[$this->normalizeText($period->getName())] = $period;
        }
        $optionByUniqueTotal = $this->buildOptionInferenceByTotalAmount($product);

        $canonical = [];
        $rawRows = 0;
        foreach ($sources as $sourceInfo) {
            try {
                $sourceRows = $this->loadCsvRows((string) $sourceInfo['path']);
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to read CSV "%s": %s', (string) $sourceInfo['path'], $e->getMessage()));

                return Command::FAILURE;
            }

            foreach ($sourceRows as $row) {
                ++$rawRows;
                $record = $this->toCanonicalRecord($row, (string) $sourceInfo['hint']);
                if (null === $record) {
                    continue;
                }
                $key = $this->recordKey($record);
                $canonical[$key] = isset($canonical[$key])
                    ? $this->mergeRecords($canonical[$key], $record)
                    : $record;
            }
        }

        if ($canonical === []) {
            $io->warning('No valid rows were found in provided CSV sources.');

            return Command::SUCCESS;
        }

        $createdApps = 0;
        $updatedApps = 0;
        $createdPayments = 0;
        $skipped = 0;

        foreach ($canonical as $rowKey => $record) {
            $email = $this->normalizeEmail((string) $record['email']);
            $name = trim((string) $record['name']);
            $phone = trim((string) $record['phone']);
            $legacyUuid = trim((string) $record['applicationUuid']);

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $io->warning(sprintf('Row key %s skipped: invalid email.', (string) $rowKey));
                ++$skipped;
                continue;
            }

            $option = $this->resolveParticipationOption(
                trim((string) $record['participationOptionName']),
                $optionsByName,
                $optionsByCode
            );
            if (!$option) {
                $option = $optionByUniqueTotal[(int) $record['totalAmount']] ?? null;
            }
            if (!$option) {
                $io->warning(sprintf(
                    'Row key %s skipped: cannot resolve participation option "%s".',
                    (string) $rowKey,
                    (string) $record['participationOptionName']
                ));
                ++$skipped;
                continue;
            }

            $period = $this->resolvePricingPeriod($record, $periodsByName, $periods, $product, $option);
            if (!$period) {
                $io->warning(sprintf('Row key %s skipped: cannot resolve pricing period.', (string) $rowKey));
                ++$skipped;
                continue;
            }

            $normalizedPhone = PhoneNormalizer::toE164($phone) ?? $phone;
            $user = $this->findOrCreateUser($name !== '' ? $name : $email, $email, $normalizedPhone);
            $application = $this->findOrCreateApplication($legacyUuid, $email, $user, $product);
            $isNewApp = null === $application->getId();

            $importedTotalAmount = max(0, (int) $record['totalAmount']);
            $payNowAmount = max(0, (int) $record['payNowAmount']);
            $paidTotal = max(0, (int) $record['paidTotal']);
            $paymentsTotal = 0;

            $currentTotalAmount = max(0, $application->getTotalAmount());
            $totalAmount = $this->resolveTotalAmount($currentTotalAmount, $importedTotalAmount, $paidTotal, $payNowAmount);

            $application->setUser($user);
            $application->setProduct($product);
            $application->setPricingPeriod($period);
            $application->setTotalAmount($totalAmount);

            $payload = $application->getPayload();
            $payload['participationOptionCode'] = $option->getCode();
            $payload['participationOptionName'] = $option->getName();
            $payload['pricingPeriodName'] = $period->getName();
            $payload['payNowAmount'] = $payNowAmount;
            $payload['adultsCount'] = max(1, (int) $record['adultsCount']);
            $payload['childrenCount'] = max(0, (int) $record['childrenCount']);
            $payload['transferIncluded'] = (bool) $record['transferIncluded'];
            $payload['paymentFactor'] = (float) $record['paymentFactor'];
            if ($legacyUuid !== '') {
                $payload['legacyApplicationUuid'] = $legacyUuid;
            }
            if (!empty($record['sourceTag'])) {
                $payload['legacySource'] = (string) $record['sourceTag'];
            }
            $application->setPayload($payload);
            $this->entityManager->persist($application);

            if ($isNewApp) {
                ++$createdApps;
            } else {
                ++$updatedApps;
            }

            foreach ($record['payments'] as $index => $slot) {
                [$created, $amount] = $this->importPaymentSlot(
                    $application,
                    (array) $slot,
                    sprintf('%s-slot-%d', $legacyUuid !== '' ? $legacyUuid : (string) $application->getUuid(), $index + 1),
                    $record['submittedAt']
                );
                $createdPayments += $created;
                $paymentsTotal += $amount;
            }

            if ($paymentsTotal <= 0 && $paidTotal > 0) {
                [$created, $amount] = $this->importPaymentSlot(
                    $application,
                    [
                        'id' => '',
                        'amount' => $paidTotal,
                        'paidAt' => $record['submittedAt'],
                    ],
                    sprintf('legacy-auto-%s', $legacyUuid !== '' ? $legacyUuid : (string) $application->getUuid()),
                    $record['submittedAt']
                );
                $createdPayments += $created;
                $paymentsTotal += $amount;
            }

            $finalPaid = max($application->getPaidAmount(), $paidTotal, $paymentsTotal);
            if ($totalAmount > 0) {
                $finalPaid = min($finalPaid, $totalAmount);
            }

            $application->setPaidAmount($finalPaid);
            $application->setStatus($this->resolveStatus($finalPaid, $totalAmount));
        }

        if ($dryRun) {
            $this->entityManager->clear();
            $io->success(sprintf(
                'Dry-run done. raw_rows=%d, merged_rows=%d, created_apps=%d, updated_apps=%d, created_payments=%d, skipped=%d',
                $rawRows,
                count($canonical),
                $createdApps,
                $updatedApps,
                $createdPayments,
                $skipped
            ));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Import done. raw_rows=%d, merged_rows=%d, created_apps=%d, updated_apps=%d, created_payments=%d, skipped=%d',
            $rawRows,
            count($canonical),
            $createdApps,
            $updatedApps,
            $createdPayments,
            $skipped
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadCsvRows(string $source): array
    {
        $content = (string) @file_get_contents($source);
        if ($content === '') {
            return [];
        }

        $firstLine = strtok($content, "\n");
        if (!is_string($firstLine) || $firstLine === '') {
            return [];
        }

        $delimiter = $this->detectDelimiter($firstLine);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!is_array($headers)) {
            fclose($stream);

            return [];
        }

        $headerKeys = [];
        foreach ($headers as $idx => $header) {
            $key = $this->normalizeHeader((string) $header);
            if ($key === '') {
                $key = sprintf('col_%d', $idx);
            }
            if (isset($headerKeys[$key])) {
                $key = sprintf('%s__%d', $key, $idx);
            }
            $headerKeys[$idx] = $key;
        }

        $rows = [];
        while (($cols = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $row = [];
            foreach ($headerKeys as $idx => $key) {
                $row[$key] = isset($cols[$idx]) ? trim((string) $cols[$idx]) : '';
            }
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    private function detectDelimiter(string $headerLine): string
    {
        $candidates = [',', ';', "\t"];
        $best = ',';
        $bestCount = -1;
        foreach ($candidates as $candidate) {
            $count = substr_count($headerLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/u', '', $header) ?? $header;

        return mb_strtolower(preg_replace('/\s+/', '', trim($header)) ?? '');
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function findOrCreateUser(string $name, string $email, string $phone): User
    {
        $email = $this->normalizeEmail($email);
        if (isset($this->userByEmailCache[$email])) {
            $user = $this->userByEmailCache[$email];
            $user->setName($name);
            $user->setPhone($phone !== '' ? $phone : null);
            $this->entityManager->persist($user);

            return $user;
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
        }

        $user->setName($name);
        $user->setPhone($phone !== '' ? $phone : null);
        $this->entityManager->persist($user);
        $this->userByEmailCache[$email] = $user;

        return $user;
    }

    private function findOrCreateApplication(string $legacyUuid, string $email, User $user, Product $product): Application
    {
        if ($legacyUuid !== '') {
            $existing = $this->applicationRepository->findOneByUuid($legacyUuid);
            if ($existing) {
                $cacheKey = $this->applicationCacheKey($product, $email);
                $this->applicationByProductEmailCache[$cacheKey] = $existing;

                return $existing;
            }
        }

        $cacheKey = $this->applicationCacheKey($product, $email);
        if (isset($this->applicationByProductEmailCache[$cacheKey])) {
            return $this->applicationByProductEmailCache[$cacheKey];
        }

        $existingByEmail = $this->applicationRepository->findActiveDuplicateByEmail($email, $product);
        if ($existingByEmail) {
            $this->applicationByProductEmailCache[$cacheKey] = $existingByEmail;

            return $existingByEmail;
        }

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);

        if ($legacyUuid !== '') {
            try {
                $application->setUuid(Uuid::fromString($legacyUuid));
            } catch (\Throwable) {
            }
        }

        $this->applicationByProductEmailCache[$cacheKey] = $application;

        return $application;
    }

    /**
     * @param array<string, mixed> $slot
     *
     * @return array{0:int,1:int}
     */
    private function importPaymentSlot(Application $application, array $slot, string $fallbackId, ?\DateTimeImmutable $fallbackDate): array
    {
        $amount = max(0, (int) ($slot['amount'] ?? 0));
        if ($amount <= 0) {
            return [0, 0];
        }

        $paymentId = trim((string) ($slot['id'] ?? ''));
        if ($paymentId === '') {
            $paymentId = $fallbackId;
        }

        $cacheKey = PaymentProvider::Yookassa->value.'|'.$paymentId;
        if (isset($this->paymentByProviderIdCache[$cacheKey])) {
            return [0, $amount];
        }

        $payment = $this->paymentRepository->findOneByProviderPaymentId(PaymentProvider::Yookassa, $paymentId);
        if ($payment) {
            $payment->setApplication($application);
            $payment->setAmount($amount);
            $payment->setStatus(PaymentStatus::Succeeded);
            $paidAt = $slot['paidAt'] ?? null;
            if ($paidAt instanceof \DateTimeImmutable) {
                $payment->setPaidAt($paidAt);
            }
            $this->paymentByProviderIdCache[$cacheKey] = $payment;

            return [0, $amount];
        }

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId($paymentId);
        $payment->setAmount($amount);
        $payment->setStatus(PaymentStatus::Succeeded);
        $paidAt = $slot['paidAt'] ?? null;
        $paidAt = $paidAt instanceof \DateTimeImmutable ? $paidAt : $fallbackDate;
        $payment->setPaidAt($paidAt ?? new \DateTimeImmutable());
        $this->entityManager->persist($payment);
        $this->paymentByProviderIdCache[$cacheKey] = $payment;

        return [1, $amount];
    }

    /**
     * @param array<string, ParticipationOption> $optionsByName
     * @param array<string, ParticipationOption> $optionsByCode
     */
    private function resolveParticipationOption(string $rawName, array $optionsByName, array $optionsByCode): ?ParticipationOption
    {
        $normalized = $this->normalizeText($rawName);
        if ($normalized !== '' && isset($optionsByName[$normalized])) {
            return $optionsByName[$normalized];
        }

        if (str_contains($normalized, 'своемжилье') && str_contains($normalized, 'безпитания')) {
            return $optionsByCode['OWN_HOUSE_NO_FOOD'] ?? null;
        }
        if (str_contains($normalized, 'своемжилье') && str_contains($normalized, 'спитанием')) {
            return $optionsByCode['OWN_HOUSE_FOOD'] ?? null;
        }
        if (str_contains($normalized, 'нашейпалатке') && str_contains($normalized, 'безпитания')) {
            return $optionsByCode['OUR_TENT_NO_FOOD'] ?? null;
        }
        if (str_contains($normalized, 'нашейпалатке') && str_contains($normalized, 'спитанием')) {
            return $optionsByCode['OUR_TENT_FOOD'] ?? null;
        }
        if (str_contains($normalized, '1день') && str_contains($normalized, 'спитанием')) {
            return $optionsByCode['ONE_DAY_FOOD'] ?? null;
        }
        if (str_contains($normalized, '1день') && str_contains($normalized, 'безпитания')) {
            return $optionsByCode['ONE_DAY'] ?? null;
        }
        if (str_contains($normalized, 'тольковоскресение') || str_contains($normalized, 'толькосуббота')) {
            return $optionsByCode['ONE_DAY'] ?? null;
        }
        if (str_contains($normalized, 'спятницынасубботу') || str_contains($normalized, 'ссубботынавоскресение')) {
            return $optionsByCode['ONE_DAY'] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, PricingPeriod> $periodsByName
     * @param list<PricingPeriod> $periods
     */
    private function resolvePricingPeriod(
        array $record,
        array $periodsByName,
        array $periods,
        Product $product,
        ParticipationOption $option
    ): ?PricingPeriod {
        $normalized = $this->normalizeText((string) ($record['pricingPeriodName'] ?? ''));
        if ($normalized !== '' && isset($periodsByName[$normalized])) {
            return $periodsByName[$normalized];
        }

        $submittedAt = $record['submittedAt'] ?? null;
        if ($submittedAt instanceof \DateTimeImmutable) {
            foreach ($periods as $period) {
                if ($submittedAt >= $period->getStartAt() && $submittedAt <= $period->getEndAt()) {
                    return $period;
                }
            }
        }

        $total = max(0, (int) ($record['totalAmount'] ?? 0));
        if ($total > 0) {
            $priceRows = $this->entityManager->getRepository(ParticipationPrice::class)->findBy([
                'participationOption' => $option,
            ]);
            foreach ($priceRows as $priceRow) {
                $period = $priceRow->getPricingPeriod();
                if ($period?->getProduct()?->getId() !== $product->getId()) {
                    continue;
                }
                if ($priceRow->getPrice() === $total) {
                    return $period;
                }
            }
        }

        return $periods !== [] ? $periods[count($periods) - 1] : null;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';

        return $value;
    }

    private function resolveTotalAmount(int $currentTotal, int $importedTotal, int $paidTotal, int $payNowAmount): int
    {
        if ($importedTotal > 0) {
            return max($currentTotal, $importedTotal);
        }

        if ($currentTotal > 0) {
            // Never downgrade already known total from "second payment" rows where total=0.
            return $currentTotal;
        }

        // Fallback for rows that have no explicit total but contain payment data.
        return max($paidTotal, $payNowAmount, 0);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function applicationCacheKey(Product $product, string $email): string
    {
        return sprintf('%s|%s', (string) $product->getId(), $this->normalizeEmail($email));
    }

    private function parseMoney(string $value): int
    {
        $clean = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';
        if ($clean === '') {
            return 0;
        }
        $clean = str_replace(',', '.', $clean);
        $float = (float) $clean;

        return (int) round($float);
    }

    private function parseInt(string $value, int $default = 0): int
    {
        if ($value === '') {
            return $default;
        }

        return (int) preg_replace('/[^\d\-]/', '', $value);
    }

    private function parseFloat(string $value, float $default = 0.0): float
    {
        if ($value === '') {
            return $default;
        }

        $clean = str_replace(',', '.', preg_replace('/[^\d,.\-]/u', '', $value) ?? '');
        if ($clean === '') {
            return $default;
        }

        return (float) $clean;
    }

    private function parseBool(string $value): bool
    {
        $value = mb_strtolower(trim($value));

        return in_array($value, ['1', 'true', 'yes', 'да'], true);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'Y-m-d H:i:s',
            \DateTimeInterface::ATOM,
        ];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $row
     *
     * @return array<string, mixed>|null
     */
    private function toCanonicalRecord(array $row, string $sourceHint): ?array
    {
        $email = $this->pick($row, ['email', 'email|email-1']);
        $name = $this->pick($row, ['name', 'фио', 'фио|name-1']);
        $phone = $this->pick($row, ['phone', 'телефон', 'телефон|phone-1']);
        $optionName = $this->pick($row, ['participationoptionname', 'вариантучастия', 'вариантучастия|select-1', 'select-1']);

        if ($email === '' && $name === '' && $phone === '') {
            return null;
        }

        $totalAmount = $this->parseMoney($this->pick($row, ['totalamount', 'итоговаястоимость', 'итоговаястоимость|calculation-1', 'calculation-1']));
        $payNowAmount = $this->parseMoney($this->pick($row, ['paynowamount', 'введитесумму', 'введитесумму|number-4', 'number-4']));

        $payment1Amount = $this->parseMoney($this->pick($row, ['payment1amount', 'оплачено']));
        $payment2Amount = $this->parseMoney($this->pick($row, ['payment2amount', 'оплаченовтораяполовина']));
        $paidTotal = $this->parseMoney($this->pick($row, ['paidtotal', 'оплаченовсего']));
        if ($paidTotal <= 0) {
            $paidTotal = $payment1Amount + $payment2Amount;
        }

        $paymentChoice = $this->pick($row, ['вариантоплаты', 'вариантоплаты|radio-1', 'radio-1']);
        $factorFromChoice = $this->paymentFactorFromChoice($paymentChoice);
        $paymentFactor = $this->parseFloat($this->pick($row, ['paymentfactor']), 0.0);
        if ($paymentFactor <= 0) {
            if ($factorFromChoice > 0) {
                $paymentFactor = $factorFromChoice;
            } elseif ($totalAmount > 0 && $payNowAmount > 0) {
                $paymentFactor = max(0.0, min(1.0, $payNowAmount / $totalAmount));
            } else {
                $paymentFactor = 1.0;
            }
        }

        return [
            'sourceTag' => $sourceHint,
            'applicationUuid' => trim($this->pick($row, ['applicationuuid'])),
            'name' => trim($name),
            'email' => trim($email),
            'phone' => trim($phone),
            'participationOptionName' => trim($optionName),
            'pricingPeriodName' => trim($this->pick($row, ['pricingperiodname'])),
            'totalAmount' => $totalAmount,
            'payNowAmount' => $payNowAmount,
            'adultsCount' => max(1, $this->parseInt($this->pick($row, ['adultscount', 'количествовзрослыхучастников', 'количествовзрослыхучастников|number-1', 'number-1']), 1)),
            'childrenCount' => max(0, $this->parseInt($this->pick($row, ['childrencount', 'количестводетейиподростковдо16лет', 'количестводетейиподростковдо16лет|number-3', 'number-3']), 0)),
            'transferIncluded' => $this->parseBool($this->pick($row, ['transferincluded', 'трансфертуданазад', 'трансфертуданазад|checkbox-3', 'checkbox-3'])),
            'paymentFactor' => $paymentFactor,
            'paidTotal' => $paidTotal,
            'submittedAt' => $this->parseSubmittedAt($this->pick($row, ['времяподачизаявки'])),
            'payments' => [
                [
                    'id' => $this->pick($row, ['payment1id', 'idтранзакции']),
                    'amount' => $payment1Amount,
                    'paidAt' => $this->parseDate($this->pick($row, ['payment1date', 'дата'])),
                ],
                [
                    'id' => $this->pick($row, ['payment2id', 'idтранзакцииоплатывторойполовины']),
                    'amount' => $payment2Amount,
                    'paidAt' => $this->parseDate($this->pick($row, ['payment2date', 'датаоплатывторойполовины'])),
                ],
            ],
        ];
    }

    private function paymentFactorFromChoice(string $choice): float
    {
        $choice = $this->normalizeText($choice);
        if ($choice === '') {
            return 0.0;
        }
        if (str_contains($choice, 'предоплата50')) {
            return 0.5;
        }
        if (str_contains($choice, 'полнаяоплата') || str_contains($choice, 'оплатавторойполовины')) {
            return 1.0;
        }

        return 0.0;
    }

    private function parseSubmittedAt(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([А-Яа-яЁё]{3})\s+(\d{1,2}),\s*(\d{4})\s*@\s*(\d{1,2}):(\d{2})\s*(ДП|ПП)$/u', $value, $m)) {
            $months = [
                'янв' => 1, 'фев' => 2, 'мар' => 3, 'апр' => 4, 'май' => 5, 'июн' => 6,
                'июл' => 7, 'авг' => 8, 'сен' => 9, 'окт' => 10, 'ноя' => 11, 'дек' => 12,
            ];
            $month = $months[mb_strtolower($m[1])] ?? null;
            if ($month) {
                $hour = (int) $m[4];
                if ($m[6] === 'ПП' && $hour < 12) {
                    $hour += 12;
                }
                if ($m[6] === 'ДП' && $hour === 12) {
                    $hour = 0;
                }

                return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Yekaterinburg')))
                    ->setDate((int) $m[3], $month, (int) $m[2])
                    ->setTime($hour, (int) $m[5], 0);
            }
        }

        return $this->parseDate($value);
    }

    /**
     * @return array<int, ParticipationOption>
     */
    private function buildOptionInferenceByTotalAmount(Product $product): array
    {
        $prices = $this->entityManager->getRepository(ParticipationPrice::class)->findAll();

        $bucket = [];
        foreach ($prices as $priceRow) {
            $period = $priceRow->getPricingPeriod();
            if (!$period || $period->getProduct()?->getId() !== $product->getId()) {
                continue;
            }
            $option = $priceRow->getParticipationOption();
            if (!$option) {
                continue;
            }
            $amount = $priceRow->getPrice();
            $bucket[$amount][$option->getCode()] = $option;
        }

        $result = [];
        foreach ($bucket as $amount => $optionsByCode) {
            if (count($optionsByCode) === 1) {
                $result[(int) $amount] = current($optionsByCode);
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $row
     * @param list<string> $aliases
     */
    private function pick(array $row, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            foreach ($row as $rowKey => $rowValue) {
                if (str_starts_with($rowKey, $key)) {
                    $value = trim((string) $rowValue);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $record
     */
    private function recordKey(array $record): string
    {
        $uuid = trim((string) ($record['applicationUuid'] ?? ''));
        if ($uuid !== '') {
            return 'uuid:'.$uuid;
        }

        $email = mb_strtolower(trim((string) ($record['email'] ?? '')));
        $phone = PhoneNormalizer::toDigits((string) ($record['phone'] ?? ''));
        $option = $this->normalizeText((string) ($record['participationOptionName'] ?? ''));

        return 'fp:'.sha1($email.'|'.$phone.'|'.$option);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $incoming
     *
     * @return array<string, mixed>
     */
    private function mergeRecords(array $base, array $incoming): array
    {
        foreach (['name', 'email', 'phone', 'participationOptionName', 'pricingPeriodName', 'applicationUuid'] as $field) {
            if (($base[$field] ?? '') === '' && ($incoming[$field] ?? '') !== '') {
                $base[$field] = $incoming[$field];
            }
        }

        foreach (['totalAmount', 'payNowAmount', 'adultsCount', 'childrenCount', 'paidTotal'] as $field) {
            $base[$field] = max((int) ($base[$field] ?? 0), (int) ($incoming[$field] ?? 0));
        }

        $base['transferIncluded'] = (bool) ($base['transferIncluded'] ?? false) || (bool) ($incoming['transferIncluded'] ?? false);
        $base['paymentFactor'] = max((float) ($base['paymentFactor'] ?? 0), (float) ($incoming['paymentFactor'] ?? 0));

        $baseDate = $base['submittedAt'] ?? null;
        $incomingDate = $incoming['submittedAt'] ?? null;
        if ($incomingDate instanceof \DateTimeImmutable && (!$baseDate instanceof \DateTimeImmutable || $incomingDate > $baseDate)) {
            $base['submittedAt'] = $incomingDate;
        }

        $payments = [];
        foreach (array_merge($base['payments'] ?? [], $incoming['payments'] ?? []) as $slot) {
            $slot = (array) $slot;
            $amount = (int) ($slot['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $slotId = trim((string) ($slot['id'] ?? ''));
            $k = $slotId !== ''
                ? 'id:'.$slotId
                : 'amt:'.$amount.'|'.(($slot['paidAt'] ?? null) instanceof \DateTimeImmutable ? $slot['paidAt']->format('c') : '');
            $payments[$k] = $slot;
        }
        $base['payments'] = array_values($payments);

        return $base;
    }

    private function resolveStatus(int $paidAmount, int $totalAmount): ApplicationStatus
    {
        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            return ApplicationStatus::Paid;
        }

        if ($paidAmount > 0) {
            return ApplicationStatus::PartiallyPaid;
        }

        return ApplicationStatus::New;
    }
}

