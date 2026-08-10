<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\ScheduleImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import:schedule',
    description: 'Import festival schedule from Google Sheets matrix CSV export',
)]
class ImportScheduleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScheduleImportService $importService,
        private readonly HttpClientInterface $httpClient,
        private readonly string $defaultSourceUrl = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'CSV file path or published Google Sheet export URL')
            ->addOption('product-slug', null, InputOption::VALUE_OPTIONAL, 'Product slug (default: active product)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and preview without writing to DB')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-import even if source hash unchanged');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $source = (string) ($input->getOption('source') ?: $this->defaultSourceUrl);
        if ($source === '') {
            $io->error('Pass --source=<csv-path-or-url> or configure SCHEDULE_SHEET_URL.');

            return Command::FAILURE;
        }

        $productSlug = (string) $input->getOption('product-slug');
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['slug' => $productSlug]);
        if (!$product) {
            $io->error(sprintf('Product "%s" not found.', $productSlug));

            return Command::FAILURE;
        }

        try {
            $csvContent = $this->loadSource($source);
        } catch (\Throwable $exception) {
            $io->error('Failed to load schedule source: '.$exception->getMessage());

            return Command::FAILURE;
        }

        try {
            $result = $this->importService->importFromCsv(
                $product,
                $csvContent,
                str_starts_with($source, 'http') ? $source : null,
                $dryRun,
                $force,
            );
        } catch (\InvalidArgumentException $exception) {
            $io->error('Schedule parse failed: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->success(sprintf(
                'Dry run OK: %d venues, %d events parsed.',
                $result['venues'],
                $result['events'],
            ));

            return Command::SUCCESS;
        }

        if ($result['skipped']) {
            $io->note(sprintf(
                'No changes detected (%d venues, %d events). Import skipped.',
                $result['venues'],
                $result['events'],
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Schedule imported: %d venues, %d events.',
            $result['venues'],
            $result['events'],
        ));

        return Command::SUCCESS;
    }

    private function loadSource(string $source): string
    {
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            $response = $this->httpClient->request('GET', $source);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new \RuntimeException(sprintf('HTTP %d from %s', $status, $source));
            }

            return $response->getContent();
        }

        if (!is_readable($source)) {
            throw new \RuntimeException(sprintf('File not readable: %s', $source));
        }

        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read file: %s', $source));
        }

        return $content;
    }
}
