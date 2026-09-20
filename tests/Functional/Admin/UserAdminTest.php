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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class UserAdminTest extends WebTestCase
{
    public function testIndexHasNoCreateButton(): void
    {
        $client = $this->adminClientWithUser();
        $client->request('GET', '/admin/user');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content-header', 'Пользователи');
        self::assertSelectorTextContains('table.datagrid', 'Админ Пользователь');
        self::assertSelectorNotExists('a.action-new');
        self::assertSelectorNotExists('table.datagrid a.action-edit');
        self::assertSelectorExists('table.datagrid tr[data-default-action-url]');
    }

    public function testDetailListsApplicationsAndPayments(): void
    {
        $client = $this->adminClientWithUser();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin-user@test.example']);
        self::assertNotNull($user);

        $client->request('GET', '/admin/user/'.$user->getId());

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Заявки', $html);
        self::assertStringContainsString('Платежи', $html);
        self::assertStringContainsString('Админ Пользователь', $html);
        self::assertStringContainsString('1 800', $html);
        self::assertSelectorNotExists('a.action-edit');
        self::assertSelectorNotExists('a.action-new');
    }

    private function adminClientWithUser(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
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
        $person->setName('Админ Пользователь');
        $person->setEmail('admin-user@test.example');
        $person->setPhone('+79160000666');
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
        $payment->setProviderPaymentId('yk-user-detail');
        $em->persist($payment);
        $em->flush();

        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return $client;
    }
}
