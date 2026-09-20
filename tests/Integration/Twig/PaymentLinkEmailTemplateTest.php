<?php

namespace App\Tests\Integration\Twig;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

#[Group('integration')]
final class PaymentLinkEmailTemplateTest extends KernelTestCase
{
    public function testPartialPaymentEmailUsesBrandAndCta(): void
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        $html = $twig->render('email/payment_link.html.twig', [
            'name' => 'Анна',
            'paidAmount' => 1800,
            'remainingAmount' => 1800,
            'totalAmount' => 3600,
            'payUrl' => 'https://example.test/pay/token',
            'logoUrl' => 'https://example.test/logo.png',
            'siteUrl' => 'https://хануманфест.рф',
        ]);

        self::assertStringContainsString('Здравствуйте, Анна', $html);
        self::assertStringContainsString('предоплата принята', $html);
        self::assertStringContainsString('Оплатить остаток', $html);
        self::assertStringContainsString('#f65414', $html);
        self::assertStringContainsString('https://example.test/pay/token', $html);
        self::assertStringNotContainsString('Hanuman Fest', $html);
    }
}
