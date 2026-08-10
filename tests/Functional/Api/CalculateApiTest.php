<?php

namespace App\Tests\Functional\Api;

use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class CalculateApiTest extends WebTestCase
{
    public function testCalculateEndpointReturnsPricing(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
        $optionId = $em->getRepository(\App\Entity\ParticipationOption::class)
            ->findOneBy([])
            ?->getId();

        $client->request(
            'POST',
            '/api/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'participationOptionId' => $optionId,
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(3600, $payload['totalAmount']);
        self::assertSame(1800, $payload['payNowAmount']);
    }
}
