<?php

namespace App\Command;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Enum\ApplicationStatus;
use App\Service\PaymentLinkService;
use App\Service\PaymentNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:payment-links:generate',
    description: 'Generate payment links for partially paid applications (50% and similar)',
)]
class GeneratePaymentLinksCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentLinkService $paymentLinkService,
        private readonly PaymentNotificationService $paymentNotificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('product-slug', null, InputOption::VALUE_OPTIONAL, 'Limit to one product slug')
            ->addOption('send-email', null, InputOption::VALUE_NONE, 'Send email notification with generated link')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without database changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $productSlug = $input->getOption('product-slug');
        $sendEmail = (bool) $input->getOption('send-email');
        $dryRun = (bool) $input->getOption('dry-run');

        $qb = $this->entityManager->getRepository(Application::class)->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->innerJoin('a.product', 'p')
            ->andWhere('a.status = :status')
            ->andWhere('a.paidAmount > 0')
            ->andWhere('a.paidAmount < a.totalAmount')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM '.PaymentLink::class.' pl WHERE pl.application = a
            )')
            ->setParameter('status', ApplicationStatus::PartiallyPaid);

        if (is_string($productSlug) && $productSlug !== '') {
            $qb->andWhere('p.slug = :slug')->setParameter('slug', $productSlug);
        }

        /** @var list<Application> $applications */
        $applications = $qb->getQuery()->getResult();
        if ($applications === []) {
            $io->success('No partially paid applications without payment links found.');

            return Command::SUCCESS;
        }

        $created = 0;
        $previewRows = [];

        foreach ($applications as $application) {
            $remaining = $application->getRemainingAmount();
            if ($remaining <= 0) {
                continue;
            }

            $user = $application->getUser();
            $previewRows[] = [
                (string) $application->getUuid(),
                $user?->getEmail() ?? '—',
                (string) $application->getPaidAmount(),
                (string) $application->getTotalAmount(),
                (string) $remaining,
            ];

            if ($dryRun) {
                ++$created;
                continue;
            }

            $paymentLink = $this->paymentLinkService->createForApplication($application);
            ++$created;

            if ($sendEmail) {
                $this->paymentNotificationService->sendPartialPaymentEmail($application, $paymentLink);
            }
        }

        if ($previewRows !== []) {
            $io->table(['UUID', 'Email', 'Paid', 'Total', 'Remaining'], $previewRows);
        }

        if ($dryRun) {
            $io->success(sprintf('Dry-run: links_to_create=%d', $created));

            return Command::SUCCESS;
        }

        $message = sprintf('Done: created_links=%d', $created);
        if ($sendEmail) {
            $message .= sprintf(', emails_sent=%d', $created);
        }
        $io->success($message);

        return Command::SUCCESS;
    }
}
