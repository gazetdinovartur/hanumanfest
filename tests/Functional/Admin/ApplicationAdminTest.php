<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\PaymentLink;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentStatus;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Uid\Uuid;

#[Group('functional')]
final class ApplicationAdminTest extends WebTestCase
{
    public function testIndexIsReadOnlyAndOpensDetailOnRowClick(): void
    {
        [$client, $application] = $this->seedApplication();
        $client->request('GET', '/admin/application');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Заявки', $html);
        self::assertStringContainsString('Админ Заявка', $html);
        self::assertStringContainsString('Новая', $html);
        self::assertSelectorNotExists('a.action-new');
        self::assertSelectorNotExists('table.datagrid a.action-edit');
        self::assertSelectorExists('table.datagrid tr[data-default-action-url]');
        self::assertStringContainsString('/admin/application/'.$application->getId(), $html);
        self::assertStringNotContainsString('/admin/application/'.$application->getId().'/edit', $html);
        self::assertSelectorExists('table.datagrid a.action-delete[aria-label="Удалить"]');
        self::assertStringNotContainsString('11111111-2222-4333-8444-555555555555', $html);
        self::assertStringNotContainsString('>UUID<', $html);
    }

    public function testDetailShowsRegistrationData(): void
    {
        [$client, $application] = $this->seedApplication([
            'adultsCount' => 2,
            'childrenCount' => 1,
            'participationOptionName' => 'в своей палатке',
            'pricingPeriodName' => 'До 10 марта',
            'paymentFactor' => 0.5,
            'transferIncluded' => true,
            'tentRoommate' => 'Анна',
            'payNowAmount' => 1800,
        ]);

        $client->request('GET', '/admin/application/'.$application->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content-header', 'Админ Заявка');
        self::assertSelectorTextNotContains('.content-header', '₽');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Данные формы', $html);
        self::assertStringContainsString('Вариант участия', $html);
        self::assertStringContainsString('в своей палатке', $html);
        self::assertStringContainsString('Ценовой период', $html);
        self::assertStringContainsString('До 10 марта', $html);
        self::assertStringContainsString('Взрослых', $html);
        self::assertStringContainsString('Трансфер', $html);
        self::assertStringContainsString('Да', $html);
        self::assertStringContainsString('Предоплата 50%', $html);
        self::assertStringContainsString('Анна', $html);
        self::assertStringContainsString('3 600 ₽', $html);
        self::assertStringContainsString('1 800 ₽', $html);
        self::assertStringNotContainsString('paymentFactor', $html);
        self::assertStringNotContainsString('participationOptionId', $html);
        self::assertSelectorNotExists('a.action-edit');
    }

    public function testDetailShowsPaymentLinkAsUrl(): void
    {
        [$client, $application] = $this->seedApplication();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $link = new PaymentLink();
        $application->addPaymentLink($link);
        $em->persist($link);
        $em->flush();

        $client->request('GET', '/admin/application/'.$application->getId());

        self::assertResponseIsSuccessful();
        $payPath = '/pay/'.$link->getToken();
        self::assertSelectorExists('a.hf-admin-pay-link[href$="'.$payPath.'"]');
        self::assertSelectorTextContains('a.hf-admin-pay-link', $payPath);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: KernelBrowser, 1: Application}
     */
    private function seedApplication(array $payload = []): array
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);

        $person = new User();
        $person->setName('Админ Заявка');
        $person->setEmail('admin-app@test.example');
        $person->setPhone('+79160000999');
        $em->persist($person);

        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUuid(Uuid::fromString('11111111-2222-4333-8444-555555555555'));
        $application->setUser($person);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::New);
        $application->setTotalAmount(3600);
        $application->setPayload($payload);
        $em->persist($application);

        if ($payload !== []) {
            $payment = new Payment();
            $payment->setApplication($application);
            $payment->setAmount(1800);
            $payment->setStatus(PaymentStatus::Pending);
            $payment->setProviderPaymentId('yk-app-detail');
            $em->persist($payment);
        }

        $em->flush();

        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return [$client, $application];
    }
}
