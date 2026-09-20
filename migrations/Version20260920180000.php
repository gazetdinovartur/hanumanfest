<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registration test mode flag and application.is_test';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD registration_test_mode TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE application ADD is_test TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings DROP registration_test_mode');
        $this->addSql('ALTER TABLE application DROP is_test');
    }
}
