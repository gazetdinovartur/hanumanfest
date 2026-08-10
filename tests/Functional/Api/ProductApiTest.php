<?php

namespace App\Tests\Functional\Api;

use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ProductApiTest extends WebTestCase
{
    public function testProductEndpointReturnsActivePeriodAndOptions(): void
    {
        $client = static::createClient();
        $this->seedDatabase($client);

        $client->request('GET', '/api/product');

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Hanuman Fest', $payload['name']);
        self::assertNotEmpty($payload['participationOptions']);
        self::assertSame('До 10 марта', $payload['activePricingPeriod']['name']);
        self::assertSame(3600, $payload['participationOptions'][0]['price']);
    }

    public function testProductEndpointReturns404WhenMissing(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $client->request('GET', '/api/product');

        self::assertResponseStatusCodeSame(404);
    }

    private function seedDatabase(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
    }
}
