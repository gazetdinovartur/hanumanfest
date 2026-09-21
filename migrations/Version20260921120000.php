<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Single transfer price on product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD transfer_price INT NOT NULL DEFAULT 600');
        $this->addSql('UPDATE product p SET p.transfer_price = COALESCE((SELECT pp.transfer_price FROM pricing_period pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1), 600)');
        $this->addSql('ALTER TABLE pricing_period DROP transfer_price');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_period ADD transfer_price INT NOT NULL DEFAULT 600');
        $this->addSql('UPDATE pricing_period pp INNER JOIN product p ON p.id = pp.product_id SET pp.transfer_price = p.transfer_price');
        $this->addSql('ALTER TABLE product DROP transfer_price');
    }
}
