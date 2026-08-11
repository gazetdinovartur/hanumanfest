<?php

namespace App\Tests\Functional\Api;

use App\Entity\Application;
use App\Entity\ParticipationOption;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ApplicationCreateApiTest extends WebTestCase
{
    public function testCreatesApplicationWithTentRoommateForOurTentOption(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy([]);
        self::assertNotNull($product);
        self::assertNotNull($period);

        $option = new ParticipationOption();
        $option->setProduct($product);
        $option->setCode('OUR_TENT_FOOD');
        $option->setName('в нашей палатке, с питанием');
        $em->persist($option);

        $price = new \App\Entity\ParticipationPrice();
        $price->setPricingPeriod($period);
        $price->setParticipationOption($option);
        $price->setPrice(7400);
        $em->persist($price);
        $em->flush();

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Тест Палатка',
                'email' => 'tent-roommate@example.com',
                'phone' => '+79001234567',
                'participationOptionId' => $option->getId(),
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 1,
                'tentRoommate' => 'С сестрой Марией',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $application = $em->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($application);
        self::assertSame('С сестрой Марией', $application->getPayload()['tentRoommate'] ?? null);
    }

    public function testDropsTentRoommateForOwnHouseOption(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Тест Домик',
                'email' => 'house-booking@example.com',
                'phone' => '+79007654321',
                'participationOptionId' => $option->getId(),
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 1,
                'tentRoommate' => 'не должно сохраниться',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $application = $em->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($application);
        self::assertArrayNotHasKey('tentRoommate', $application->getPayload());
    }

    private function bootSchema(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
    }
}
