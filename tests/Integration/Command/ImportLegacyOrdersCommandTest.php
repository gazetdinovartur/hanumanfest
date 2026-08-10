<?php

namespace App\Tests\Integration\Command;

use App\Command\ImportLegacyOrdersCommand;
use App\Command\SeedHanumanFestCommand;
use App\Entity\Application;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class ImportLegacyOrdersCommandTest extends DatabaseTestCase
{
    public function testImportsLegacyCsvRow(): void
    {
        $seed = static::getContainer()->get(SeedHanumanFestCommand::class);
        (new CommandTester($seed))->execute([]);

        $fixture = dirname(__DIR__, 2).'/fixtures/legacy-import.csv';

        $command = static::getContainer()->get(ImportLegacyOrdersCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([
            '--source' => $fixture,
            '--product-slug' => 'hanuman-fest',
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($application);
        self::assertSame('test-import@example.com', $application->getUser()?->getEmail());
        self::assertSame(3600, $application->getTotalAmount());
        self::assertSame(1800, $application->getPaidAmount());
    }
}
