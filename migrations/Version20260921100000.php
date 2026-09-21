<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transfer price per pricing period';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_period ADD transfer_price INT NOT NULL DEFAULT 600');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_period DROP transfer_price');
    }
}
