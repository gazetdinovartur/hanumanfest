<?php

namespace App\Tests\Functional\Api;

use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ScheduleApiTest extends WebTestCase
{
    public function testScheduleEndpointReturnsImportedEvents(): void
    {
        $client = static::createClient();
        $this->seedDatabase($client);

        $csv = file_get_contents(__DIR__.'/../../fixtures/schedule-matrix-sample.csv');
        self::assertNotFalse($csv);

        $importService = static::getContainer()->get(\App\Service\ScheduleImportService::class);
        $product = static::getContainer()->get('doctrine')->getRepository(\App\Entity\Product::class)
            ->findOneBy(['slug' => 'hanuman-fest']);
        self::assertNotNull($product);

        $importService->importFromCsv($product, $csv);

        $client->request('GET', '/api/product/schedule');
        self::assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('name', $payload['product']);
        self::assertNotEmpty($payload['days']);
        self::assertNotNull($payload['importedAt']);

        $firstDay = $payload['days'][0];
        self::assertSame('2026-06-26', $firstDay['date']);
        self::assertNotEmpty($firstDay['venues']);

        $allTitles = [];
        foreach ($payload['days'] as $day) {
            foreach ($day['venues'] as $venue) {
                foreach ($venue['events'] as $event) {
                    $allTitles[] = $event['title'];
                }
            }
        }

        self::assertContains('Универсальная йога с Андреем Плетнёвым', $allTitles);
        self::assertNotContains('чек. занято', $allTitles);
        self::assertContains('Завтрак', $allTitles);

        $yoga = null;
        foreach ($payload['days'] as $day) {
            foreach ($day['venues'] as $venue) {
                foreach ($venue['events'] as $event) {
                    if ($event['title'] === 'Универсальная йога с Андреем Плетнёвым') {
                        $yoga = $event;
                    }
                }
            }
        }
        self::assertNotNull($yoga);
        self::assertStringContainsString('+03:00', $yoga['startsAt']);
        self::assertStringContainsString('T16:00:00', $yoga['startsAt']);
    }

    public function testScheduleResponseHasCacheHeaders(): void
    {
        $client = static::createClient();
        $this->seedDatabase($client);

        $client->request('GET', '/api/product/schedule');
        self::assertResponseIsSuccessful();

        $response = $client->getResponse();
        self::assertTrue($response->isCacheable());
        self::assertSame('max-age=300, public', $response->headers->get('Cache-Control'));
    }

    public function testScheduleResponseIncludesCorsHeadersForMatchingOrigin(): void
    {
        $client = static::createClient();
        $this->seedDatabase($client);

        $client->request(
            'GET',
            '/api/product/schedule',
            server: ['HTTP_ORIGIN' => 'http://localhost'],
        );
        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }


    private function seedDatabase(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
    }
}
