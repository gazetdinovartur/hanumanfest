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
            'details' => [
                ['label' => 'Имя', 'value' => 'Анна'],
                ['label' => 'Email', 'value' => 'anna@example.com'],
                ['label' => 'Вариант участия', 'value' => 'Палатка'],
            ],
            'contacts' => [
                ['href' => 'mailto:hanuman-yoga@bk.ru', 'label' => 'hanuman-yoga@bk.ru'],
                ['href' => 'https://t.me/Hanuman_ekb', 'label' => 'Telegram'],
            ],
            'paidAmount' => 1800,
            'remainingAmount' => 1800,
            'totalAmount' => 3600,
            'payUrl' => 'https://example.test/pay/token',
            'logoUrl' => 'https://example.test/logo.png',
            'siteUrl' => 'https://хануманфест.рф',
        ]);

        self::assertStringContainsString('Здравствуйте, Анна', $html);
        self::assertStringContainsString('Вы зарегистрировались и внесли предоплату за участие в Хануман Фест!', $html);
        self::assertStringContainsString('Палатка', $html);
        self::assertStringContainsString('hanuman-yoga@bk.ru', $html);
        self::assertStringContainsString('Если есть вопросы, напишите нам', $html);
        self::assertStringContainsString('Оплатить остаток', $html);
        self::assertTrue(strpos($html, 'Если есть вопросы') < strpos($html, 'Оплатить остаток'));
        self::assertStringContainsString('#f65414', $html);
        self::assertStringContainsString('https://example.test/pay/token', $html);
        self::assertStringNotContainsString('Hanuman Fest', $html);
        self::assertStringNotContainsString('предоплата принята', $html);
    }
}
