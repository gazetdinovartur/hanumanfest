<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Structured site contact fields instead of contacts blob';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, contacts_html, vk_url, telegram_url, notification_email FROM site_settings',
        );

        $this->addSql('ALTER TABLE site_settings ADD phone VARCHAR(64) DEFAULT NULL, ADD phone2 VARCHAR(64) DEFAULT NULL, ADD email VARCHAR(255) DEFAULT NULL, ADD facebook_url VARCHAR(255) DEFAULT NULL, ADD instagram_url VARCHAR(255) DEFAULT NULL');

        foreach ($rows as $row) {
            $parsed = $this->parseContactsHtml((string) ($row['contacts_html'] ?? ''));
            $vk = $this->nonEmpty($row['vk_url'] ?? null) ?? $parsed['vk'];
            $telegram = $this->nonEmpty($row['telegram_url'] ?? null) ?? $parsed['telegram'];
            $email = 'hanuman-yoga@bk.ru';

            $this->addSql(
                'UPDATE site_settings SET phone = ?, phone2 = ?, email = ?, vk_url = ?, telegram_url = ?, facebook_url = ?, instagram_url = ? WHERE id = ?',
                [
                    $parsed['phone'],
                    $parsed['phone2'],
                    $email,
                    $vk,
                    $telegram,
                    $parsed['facebook'],
                    $parsed['instagram'],
                    $row['id'],
                ],
            );
        }

        $this->addSql('ALTER TABLE site_settings DROP contacts_html, DROP notification_email');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD contacts_html LONGTEXT DEFAULT NULL, ADD notification_email VARCHAR(255) DEFAULT NULL');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, phone, phone2, email, vk_url, telegram_url, facebook_url, instagram_url FROM site_settings',
        );
        foreach ($rows as $row) {
            $lines = array_values(array_filter([
                $this->nonEmpty($row['phone'] ?? null),
                $this->nonEmpty($row['phone2'] ?? null),
                $this->nonEmpty($row['email'] ?? null),
                $this->nonEmpty($row['vk_url'] ?? null),
                $this->nonEmpty($row['facebook_url'] ?? null),
                $this->nonEmpty($row['instagram_url'] ?? null),
                $this->nonEmpty($row['telegram_url'] ?? null),
            ]));
            $this->addSql(
                'UPDATE site_settings SET contacts_html = ?, notification_email = ? WHERE id = ?',
                [implode("\n", $lines), $this->nonEmpty($row['email'] ?? null), $row['id']],
            );
        }

        $this->addSql('ALTER TABLE site_settings DROP phone, DROP phone2, DROP email, DROP facebook_url, DROP instagram_url');
    }

    /**
     * @return array{phone: ?string, phone2: ?string, vk: ?string, telegram: ?string, facebook: ?string, instagram: ?string}
     */
    private function parseContactsHtml(string $raw): array
    {
        $phones = [];
        $vk = $telegram = $facebook = $instagram = null;
        $parts = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        foreach ($parts as $line) {
            $item = trim((string) $line);
            if ($item === '') {
                continue;
            }
            if (str_contains($item, 'vk.com')) {
                $vk = $item;
                continue;
            }
            if (str_contains($item, 't.me')) {
                $telegram = $item;
                continue;
            }
            if (str_contains($item, 'facebook.com')) {
                $facebook = $item;
                continue;
            }
            if (str_contains($item, 'instagram.com')) {
                $instagram = $item;
                continue;
            }
            if (1 === preg_match('/\+?\d[\d\(\)\-\s]+$/u', $item)) {
                $phones[] = $item;
            }
        }

        return [
            'phone' => $phones[0] ?? null,
            'phone2' => $phones[1] ?? null,
            'vk' => $vk,
            'telegram' => $telegram,
            'facebook' => $facebook,
            'instagram' => $instagram,
        ];
    }

    private function nonEmpty(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
