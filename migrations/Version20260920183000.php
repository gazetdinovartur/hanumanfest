<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Payment links have no expiry date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_link CHANGE expires_at expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE payment_link SET expires_at = DATE_ADD(created_at, INTERVAL 90 DAY) WHERE expires_at IS NULL");
        $this->addSql('ALTER TABLE payment_link CHANGE expires_at expires_at DATETIME NOT NULL');
    }
}
