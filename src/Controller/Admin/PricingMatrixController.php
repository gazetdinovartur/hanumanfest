<?php

namespace App\Controller\Admin;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Admin\AdminSeasonContext;
use App\Service\RegistrationTestMode;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Single-sheet admin: all pricing periods × participation options.
 */
#[IsGranted('ROLE_ADMIN')]
final class PricingMatrixController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly AdminSeasonContext $seasonContext,
        private readonly RegistrationTestMode $registrationTestMode,
    ) {
    }

    #[AdminRoute(path: '/pricing', name: 'pricing_matrix', options: ['methods' => ['GET', 'POST']])]
    public function matrix(Request $request): Response
    {
        $product = $this->productRepository->findActiveProduct();
        if (!$product) {
            throw $this->createNotFoundException('Активный продукт не найден.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('pricing_matrix', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $season = $this->requireSelectedSeason();
            $errors = $this->saveMatrix($request, $product, $season);
            if ($errors === []) {
                $this->em->flush();
                $this->addFlash('success', 'Лист периодов и цен сохранён.');

                return $this->redirectToRoute('admin_pricing_matrix');
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        $options = $this->sortOptionsByFestivalOrder(
            $this->em->getRepository(ParticipationOption::class)->findBy(['product' => $product])
        );
        if ($this->registrationTestMode->isEnabled()) {
            $this->registrationTestMode->ensureTestOption($product);
            $this->em->flush();
            $options = $this->sortOptionsByFestivalOrder(
                $this->em->getRepository(ParticipationOption::class)->findBy(['product' => $product])
            );
        }
        $options = $this->registrationTestMode->filterForPricingMatrix($options);

        return $this->renderMatrix($product, $options, $request->isMethod('POST') ? $request : null);
    }

    #[Route('/admin/pricing-period/{id}/prices', name: 'admin_pricing_period_prices', methods: ['GET', 'POST'])]
    public function legacyPeriodPrices(PricingPeriod $pricingPeriod): Response
    {
        return $this->redirectToRoute('admin_pricing_matrix');
    }

    /**
     * @param list<ParticipationOption> $options
     */
    private function renderMatrix(Product $product, array $options, ?Request $request): Response
    {
        $periods = $this->em->getRepository(PricingPeriod::class)->findBy(
            ['product' => $product, 'season' => $this->requireSelectedSeason()],
            ['startAt' => 'ASC', 'endAt' => 'ASC'],
        );
        $priceMap = $this->buildPriceMap($periods);

        $columns = [];
        $optionRows = [];
        $formPrices = [];

        if ($request) {
            $submittedPeriods = $request->request->all('periods');
            $submittedOptions = $request->request->all('options');
            $submittedPrices = $request->request->all('prices');
            $deletedPeriods = array_map('strval', (array) $request->request->all('delete_periods'));
            $deletedOptions = array_map('strval', (array) $request->request->all('delete_options'));

            foreach ($submittedOptions as $key => $row) {
                $key = (string) $key;
                if (in_array($key, $deletedOptions, true)) {
                    continue;
                }
                $optionRows[] = [
                    'key' => $key,
                    'isNew' => str_starts_with($key, 'new_'),
                    'name' => (string) ($row['name'] ?? ''),
                ];
            }

            foreach ($submittedPeriods as $key => $row) {
                $key = (string) $key;
                if (in_array($key, $deletedPeriods, true)) {
                    continue;
                }
                $columns[] = [
                    'key' => $key,
                    'isNew' => str_starts_with($key, 'new_'),
                    'name' => (string) ($row['name'] ?? ''),
                    'startAt' => (string) ($row['startAt'] ?? ''),
                    'endAt' => (string) ($row['endAt'] ?? ''),
                    'isActive' => isset($row['isActive']),
                ];
                foreach ($optionRows as $optionRow) {
                    $formPrices[$key][$optionRow['key']] = $submittedPrices[$key][$optionRow['key']] ?? 0;
                }
            }
        } else {
            foreach ($options as $option) {
                $oid = $option->getId();
                if (null === $oid) {
                    continue;
                }
                $optionRows[] = [
                    'key' => (string) $oid,
                    'isNew' => false,
                    'name' => $option->getName(),
                ];
            }

            foreach ($periods as $period) {
                $pid = $period->getId();
                if (null === $pid) {
                    continue;
                }
                $key = (string) $pid;
                $columns[] = [
                    'key' => $key,
                    'isNew' => false,
                    'name' => $period->getName(),
                    'startAt' => $period->getStartAt()->format('Y-m-d\TH:i'),
                    'endAt' => $period->getEndAt()->format('Y-m-d\TH:i'),
                    'isActive' => $period->isActive(),
                ];
                foreach ($optionRows as $optionRow) {
                    $formPrices[$key][$optionRow['key']] = $priceMap[$pid][(int) $optionRow['key']] ?? 0;
                }
            }
        }

        $columns = $this->sortPeriodColumns($columns);

        $transferPrice = $request
            ? (string) $request->request->get('transferPrice', (string) $product->getTransferPrice())
            : (string) $product->getTransferPrice();

        return $this->render('admin/pricing_matrix.html.twig', [
            'product' => $product,
            'columns' => $columns,
            'optionRows' => $optionRows,
            'formPrices' => $formPrices,
            'transferPrice' => $transferPrice,
            'optionNameWidthCh' => $this->computeOptionNameWidthCh($optionRows),
            'defaultStart' => (new \DateTimeImmutable('today'))->format('Y-m-d\T00:00'),
            'defaultEnd' => (new \DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d\T23:59'),
        ]);
    }

    /**
     * @param list<array{key: string, isNew: bool, name: string}> $optionRows
     */
    private function computeOptionNameWidthCh(array $optionRows): int
    {
        $max = 24;
        foreach ($optionRows as $row) {
            $len = mb_strlen($row['name']);
            if ($len > $max) {
                $max = $len;
            }
        }

        return min(max($max + 2, 24), 64);
    }

    /**
     * @param list<array{key: string, isNew: bool, name: string, startAt: string, endAt: string, isActive: bool}> $columns
     *
     * @return list<array{key: string, isNew: bool, name: string, startAt: string, endAt: string, isActive: bool}>
     */
    private function sortPeriodColumns(array $columns): array
    {
        usort($columns, static function (array $left, array $right): int {
            $byStart = strcmp($left['startAt'], $right['startAt']);
            if ($byStart !== 0) {
                return $byStart;
            }

            return strcmp($left['endAt'], $right['endAt']);
        });

        return $columns;
    }

    /**
     * @param list<PricingPeriod> $periods
     *
     * @return array<int, array<int, int>>
     */
    private function buildPriceMap(array $periods): array
    {
        $map = [];
        foreach ($periods as $period) {
            $pid = $period->getId();
            if (null === $pid) {
                continue;
            }
            foreach ($period->getParticipationPrices() as $price) {
                $oid = $price->getParticipationOption()?->getId();
                if (null !== $oid) {
                    $map[$pid][$oid] = $price->getPrice();
                }
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function saveMatrix(Request $request, Product $product, FestivalSeason $season): array
    {
        $errors = [];
        $transferRaw = trim((string) $request->request->get('transferPrice', ''));
        $transferPrice = preg_match('/^\d+$/', $transferRaw) ? (int) $transferRaw : null;
        if ($transferPrice === null) {
            $errors[] = 'Стоимость трансфера должна быть целым числом ≥ 0.';
        } else {
            $product->setTransferPrice($transferPrice);
        }

        $submittedPeriods = $request->request->all('periods');
        $submittedOptions = $request->request->all('options');
        $submittedPrices = $request->request->all('prices');
        $deletedPeriods = array_values(array_unique(array_map('strval', (array) $request->request->all('delete_periods'))));
        $deletedOptions = array_values(array_unique(array_map('strval', (array) $request->request->all('delete_options'))));

        $existingOptions = [];
        foreach ($this->em->getRepository(ParticipationOption::class)->findBy(['product' => $product]) as $option) {
            $existingOptions[(string) $option->getId()] = $option;
        }

        foreach ($deletedOptions as $deleteId) {
            if (!isset($existingOptions[$deleteId])) {
                continue;
            }
            $option = $existingOptions[$deleteId];
            if ($this->registrationTestMode->isTestOption($option)) {
                $errors[] = 'Нельзя удалить «Тестовая регистрация» — выключите тестовый режим в Навигации.';
                continue;
            }
            $apps = $this->countApplicationsForOption($product, (int) $deleteId);
            if ($apps > 0) {
                $errors[] = sprintf(
                    'Нельзя удалить «%s»: к варианту привязано заявок — %d.',
                    $option->getName(),
                    $apps,
                );
                continue;
            }
            $this->em->remove($option);
            unset($existingOptions[$deleteId]);
        }

        /** @var array<string, ParticipationOption> $workingOptions */
        $workingOptions = [];

        foreach ($submittedOptions as $key => $row) {
            $key = (string) $key;
            if (in_array($key, $deletedOptions, true)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $errors[] = sprintf('Вариант «%s»: укажите название.', $key);

                continue;
            }

            if (str_starts_with($key, 'new_')) {
                $option = new ParticipationOption();
                $option->setProduct($product);
                $option->setCode($this->generateUniqueOptionCode($product, $name));
            } elseif (isset($existingOptions[$key])) {
                $option = $existingOptions[$key];
            } else {
                $errors[] = sprintf('Вариант #%s не найден.', $key);

                continue;
            }

            $option->setName($name);
            if ($this->registrationTestMode->isTestOption($option)) {
                $option->setCode(RegistrationTestMode::OPTION_CODE);
                $option->setName(RegistrationTestMode::OPTION_NAME);
            }
            $this->em->persist($option);
            $workingOptions[$key] = $option;
        }

        if ($workingOptions === []) {
            $errors[] = 'Добавьте хотя бы один вариант участия.';
        }

        $existingPeriods = [];
        foreach ($this->em->getRepository(PricingPeriod::class)->findBy(['product' => $product, 'season' => $season]) as $period) {
            $existingPeriods[(string) $period->getId()] = $period;
        }

        foreach ($deletedPeriods as $deleteId) {
            if (!isset($existingPeriods[$deleteId])) {
                continue;
            }
            $period = $existingPeriods[$deleteId];
            $apps = $this->em->getRepository(Application::class)->count(['pricingPeriod' => $period]);
            if ($apps > 0) {
                $errors[] = sprintf(
                    'Нельзя удалить «%s»: к периоду привязано заявок — %d.',
                    $period->getName(),
                    $apps,
                );
                continue;
            }
            $this->em->remove($period);
            unset($existingPeriods[$deleteId], $submittedPeriods[$deleteId]);
        }

        /** @var array<string, PricingPeriod> $workingPeriods */
        $workingPeriods = [];

        foreach ($submittedPeriods as $key => $row) {
            $key = (string) $key;
            if (in_array($key, $deletedPeriods, true)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $startAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim((string) ($row['startAt'] ?? ''))) ?: null;
            $endAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim((string) ($row['endAt'] ?? ''))) ?: null;
            $isActive = isset($row['isActive']);

            if ($name === '') {
                $errors[] = sprintf('Период «%s»: укажите название.', $key);
            }
            if (!$startAt) {
                $errors[] = sprintf('Период «%s»: некорректная дата начала.', $name !== '' ? $name : $key);
            }
            if (!$endAt) {
                $errors[] = sprintf('Период «%s»: некорректная дата окончания.', $name !== '' ? $name : $key);
            }
            if ($startAt && $endAt && $endAt <= $startAt) {
                $errors[] = sprintf('Период «%s»: окончание должно быть позже начала.', $name !== '' ? $name : $key);
            }

            if (str_starts_with($key, 'new_')) {
                $period = new PricingPeriod();
                $period->setProduct($product);
                $period->setSeason($season);
            } elseif (isset($existingPeriods[$key])) {
                $period = $existingPeriods[$key];
            } else {
                $errors[] = sprintf('Период #%s не найден.', $key);

                continue;
            }

            $workingPeriods[$key] = $period;
            if ($name !== '' && $startAt && $endAt) {
                $period->setName($name);
                $period->setStartAt($startAt);
                $period->setEndAt($endAt);
                $period->setIsActive($isActive);
                $this->em->persist($period);
            }
        }

        if ($workingPeriods === []) {
            $errors[] = 'Добавьте хотя бы один период цен.';
        }

        if ($errors !== []) {
            return $errors;
        }

        foreach ($workingPeriods as $periodKey => $period) {
            $priceRows = $submittedPrices[$periodKey] ?? [];
            $existingPrices = [];
            foreach ($period->getParticipationPrices() as $price) {
                $oid = $price->getParticipationOption()?->getId();
                if (null !== $oid) {
                    $existingPrices[$oid] = $price;
                }
            }

            foreach ($workingOptions as $optionKey => $option) {
                $raw = trim((string) ($priceRows[$optionKey] ?? ''));
                if (!preg_match('/^\d+$/', $raw)) {
                    $errors[] = sprintf(
                        'Цена «%s» / «%s» должна быть целым числом ≥ 0.',
                        $period->getName(),
                        $option->getName(),
                    );

                    continue;
                }

                $lookupId = $option->getId();
                $price = ($lookupId !== null && isset($existingPrices[$lookupId]))
                    ? $existingPrices[$lookupId]
                    : new ParticipationPrice();
                $price->setPricingPeriod($period);
                $price->setParticipationOption($option);
                $price->setPrice((int) $raw);
                $period->addParticipationPrice($price);
                $this->em->persist($price);
            }
        }

        return $errors;
    }

    private function requireSelectedSeason(): FestivalSeason
    {
        $season = $this->seasonContext->getSelectedSeason();
        if (!$season) {
            throw $this->createNotFoundException('Сезон не выбран. Сначала выполните app:seed:hanuman-fest.');
        }

        return $season;
    }

    private function countApplicationsForOption(Product $product, int $optionId): int
    {
        $count = 0;
        foreach ($this->em->getRepository(Application::class)->findBy(['product' => $product]) as $application) {
            $payloadId = (int) ($application->getPayload()['participationOptionId'] ?? 0);
            if ($payloadId === $optionId) {
                ++$count;
            }
        }

        return $count;
    }

    private function generateUniqueOptionCode(Product $product, string $name): string
    {
        $transliterated = \function_exists('transliterator_transliterate')
            ? transliterator_transliterate('Any-Latin; Latin-ASCII', $name)
            : null;
        $ascii = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) $transliterated));
        $ascii = trim($ascii, '_');
        if ($ascii === '') {
            $ascii = 'OPT_'.bin2hex(random_bytes(4));
        }
        if (strlen($ascii) > 48) {
            $ascii = substr($ascii, 0, 48);
        }

        $code = $ascii;
        $suffix = 2;
        while ($this->optionCodeExists($product, $code)) {
            $code = $ascii.'_'.$suffix;
            ++$suffix;
        }

        return $code;
    }

    private function optionCodeExists(Product $product, string $code): bool
    {
        return null !== $this->em->getRepository(ParticipationOption::class)->findOneBy([
            'product' => $product,
            'code' => $code,
        ]);
    }

    /**
     * @param list<ParticipationOption> $options
     *
     * @return list<ParticipationOption>
     */
    private function sortOptionsByFestivalOrder(array $options): array
    {
        $orderMap = [
            'OWN_HOUSE_NO_FOOD' => 10,
            'OWN_HOUSE_FOOD' => 20,
            'OUR_TENT_NO_FOOD' => 30,
            'OUR_TENT_FOOD' => 40,
            'ONE_DAY' => 50,
            'ONE_DAY_FOOD' => 60,
            RegistrationTestMode::OPTION_CODE => 1000,
        ];

        usort($options, static function (ParticipationOption $left, ParticipationOption $right) use ($orderMap): int {
            $leftOrder = $orderMap[$left->getCode()] ?? 999;
            $rightOrder = $orderMap[$right->getCode()] ?? 999;
            if ($leftOrder === $rightOrder) {
                return strcmp($left->getName(), $right->getName());
            }

            return $leftOrder <=> $rightOrder;
        });

        return $options;
    }
}
