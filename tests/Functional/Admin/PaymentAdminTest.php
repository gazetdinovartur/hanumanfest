<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Application;
use App\Entity\Payment;
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

#[Group('functional')]
final class PaymentAdminTest extends WebTestCase
{
    public function testIndexHidesUnusedColumnsAndCreateAction(): void
    {
        [$client, $payment] = $this->seedPayment();
        $client->request('GET', '/admin/payment');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content-header', 'Платежи');
        self::assertSelectorExists('table.datagrid td[data-column="application"]');
        self::assertSelectorTextContains('table.datagrid td[data-column="application"]', 'Админ Платёж');
        self::assertSelectorTextNotContains('table.datagrid td[data-column="application"]', '₽');
        self::assertSelectorTextNotContains('table.datagrid td[data-column="application"]', 'Новая');
        self::assertSelectorExists('table.datagrid td[data-column="statusLabel"]');
        self::assertSelectorTextContains('table.datagrid td[data-column="statusLabel"]', 'Ожидает');
        self::assertSelectorNotExists('table.datagrid th[data-column="provider"]');
        self::assertSelectorNotExists('table.datagrid th[data-column="createdAt"]');
        self::assertSelectorNotExists('table.datagrid th[data-column="updatedAt"]');
        self::assertSelectorNotExists('a.action-new');
        self::assertSelectorNotExists('table.datagrid a.action-edit');
        self::assertSelectorExists('table.datagrid tr[data-default-action-url]');
        self::assertStringContainsString('/admin/payment/'.$payment->getId(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('/admin/payment/'.$payment->getId().'/edit', (string) $client->getResponse()->getContent());
    }

    public function testIndexShowsSucceededAndRefundedLabels(): void
    {
        [$client] = $this->seedPayment();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        $person = $em->getRepository(User::class)->findOneBy(['email' => 'admin-pay@test.example']);
        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $paidApp = new Application();
        $paidApp->setUser($person);
        $paidApp->setProduct($product);
        $paidApp->setPricingPeriod($period);
        $paidApp->setSeason($period?->getSeason());
        $paidApp->setStatus(ApplicationStatus::Paid);
        $paidApp->setTotalAmount(3600);
        $paidApp->setPaidAmount(3600);
        $paidApp->setPayload([]);
        $em->persist($paidApp);

        $succeeded = new Payment();
        $succeeded->setApplication($paidApp);
        $succeeded->setAmount(3600);
        $succeeded->setStatus(PaymentStatus::Succeeded);
        $succeeded->setProviderPaymentId('yk-admin-succeeded');
        $em->persist($succeeded);

        $refundedApp = new Application();
        $refundedApp->setUser($person);
        $refundedApp->setProduct($product);
        $refundedApp->setPricingPeriod($period);
        $refundedApp->setSeason($period?->getSeason());
        $refundedApp->setStatus(ApplicationStatus::Refunded);
        $refundedApp->setTotalAmount(1800);
        $refundedApp->setPayload([]);
        $em->persist($refundedApp);

        $refunded = new Payment();
        $refunded->setApplication($refundedApp);
        $refunded->setAmount(1800);
        $refunded->setRefundedAmount(1800);
        $refunded->setStatus(PaymentStatus::Succeeded);
        $refunded->setProviderPaymentId('yk-admin-refunded');
        $em->persist($refunded);
        $em->flush();

        $client->request('GET', '/admin/payment');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Ожидает', $html);
        self::assertStringContainsString('Успешен', $html);
        self::assertStringContainsString('Возврат', $html);
    }

    public function testDetailIsReadOnlyAndShowsHiddenIndexFields(): void
    {
        [$client, $payment] = $this->seedPayment();
        $client->request('GET', '/admin/payment/'.$payment->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content-header', 'Платёж #'.$payment->getId());
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('ЮKassa', $html);
        self::assertStringContainsString('10 августа 2026, 21:42', $html);
        self::assertStringNotContainsString('Создан', $html);
        self::assertStringNotContainsString('Обновлён', $html);
        self::assertStringContainsString('Ожидает', $html);
        self::assertSelectorNotExists('a.action-edit');
        self::assertSelectorNotExists('a.action-new');
    }

    /**
     * @return array{0: KernelBrowser, 1: Payment}
     */
    private function seedPayment(): array
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
        $person->setName('Админ Платёж');
        $person->setEmail('admin-pay@test.example');
        $person->setPhone('+79160000888');
        $em->persist($person);

        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($person);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::New);
        $application->setTotalAmount(1800);
        $application->setPayload([]);
        $em->persist($application);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setAmount(1800);
        $payment->setStatus(PaymentStatus::Pending);
        $payment->setProviderPaymentId('yk-admin-001');
        $payment->setPaidAt(new \DateTimeImmutable('2026-08-10 21:42:00', new \DateTimeZone('Europe/Moscow')));
        $em->persist($payment);
        $em->flush();

        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return [$client, $payment];
    }
}
