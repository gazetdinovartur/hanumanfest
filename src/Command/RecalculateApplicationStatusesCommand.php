<?php

namespace App\Command;

use App\Entity\Application;
use App\Repository\ApplicationRepository;
use App\Service\ApplicationBalance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:applications:recalculate-statuses',
    description: 'Recalculate paid amounts/statuses based on succeeded payments',
)]
class RecalculateApplicationStatusesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('product-slug', null, InputOption::VALUE_OPTIONAL, 'Limit to one product slug')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview updates without writing to DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $productSlug = (string) $input->getOption('product-slug');

        $qb = $this->entityManager->getRepository(Application::class)->createQueryBuilder('a');

        if ($productSlug !== '') {
            $qb
                ->leftJoin('a.product', 'p')
                ->andWhere('p.slug = :slug')
                ->setParameter('slug', $productSlug);
        }

        /** @var list<Application> $applications */
        $applications = $qb->getQuery()->getResult();
        if ($applications === []) {
            $io->success('No applications found for recalculation.');

            return Command::SUCCESS;
        }

        /** @var ApplicationRepository $applicationRepository */
        $applicationRepository = $this->entityManager->getRepository(Application::class);

        $changed = 0;
        foreach ($applications as $application) {
            $totals = $applicationRepository->succeededPaymentTotals($application);
            $newPaid = $totals['paid'];
            $newStatus = ApplicationBalance::resolveStatus($newPaid, max(0, $application->getTotalAmount()), $totals['refunded']);

            $isChanged = $newPaid !== $application->getPaidAmount() || $newStatus !== $application->getStatus();
            if (!$isChanged) {
                continue;
            }

            ++$changed;
            if ($dryRun) {
                continue;
            }

            $application->setPaidAmount($newPaid);
            $application->setStatus($newStatus);
        }

        if ($dryRun) {
            $io->success(sprintf('Dry-run: applications_to_update=%d (of %d)', $changed, count($applications)));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Done: updated=%d, scanned=%d', $changed, count($applications)));

        return Command::SUCCESS;
    }
}
