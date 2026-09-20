<?php

namespace App\Command;

use App\Entity\Payment;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:yookassa:sync-refunds',
    description: 'Sync refunded_amount for succeeded YooKassa payments via Payments API',
)]
class SyncYookassaRefundsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentService $paymentService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing refunded amounts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var list<Payment> $payments */
        $payments = $this->entityManager->getRepository(Payment::class)->findBy([
            'provider' => PaymentProvider::Yookassa,
            'status' => PaymentStatus::Succeeded,
        ]);

        $checked = 0;
        $updated = 0;
        foreach ($payments as $payment) {
            if (!$payment->getProviderPaymentId()) {
                continue;
            }

            ++$checked;
            if ($dryRun) {
                continue;
            }

            try {
                if ($this->paymentService->syncRefundsFromYookassa($payment)) {
                    ++$updated;
                }
            } catch (\Throwable $exception) {
                $io->warning(sprintf(
                    'Payment #%d (%s): %s',
                    (int) $payment->getId(),
                    (string) $payment->getProviderPaymentId(),
                    $exception->getMessage(),
                ));
            }
        }

        if ($dryRun) {
            $io->success(sprintf('Dry-run: yookassa_payments=%d', $checked));

            return Command::SUCCESS;
        }

        $io->success(sprintf('Done: checked=%d, updated=%d', $checked, $updated));

        return Command::SUCCESS;
    }
}
