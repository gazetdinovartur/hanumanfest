<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Festival seasons, application/period season FK, payment refunded amount';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE festival_season (id INT AUTO_INCREMENT NOT NULL, year INT NOT NULL, name VARCHAR(255) NOT NULL, is_current TINYINT(1) NOT NULL, UNIQUE INDEX uniq_festival_season_year (year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("INSERT INTO festival_season (year, name, is_current) VALUES (2026, 'Хануман Фест 2026', 0), (2027, 'Хануман Фест 2027', 1)");

        $this->addSql('ALTER TABLE payment ADD refunded_amount INT NOT NULL DEFAULT 0');

        $this->addSql('ALTER TABLE pricing_period ADD season_id INT DEFAULT NULL');
        $this->addSql('UPDATE pricing_period SET season_id = (SELECT id FROM festival_season WHERE year = 2026)');
        $this->addSql('ALTER TABLE pricing_period CHANGE season_id season_id INT NOT NULL');
        $this->addSql('ALTER TABLE pricing_period ADD CONSTRAINT FK_pricing_period_season FOREIGN KEY (season_id) REFERENCES festival_season (id)');
        $this->addSql('CREATE INDEX IDX_pricing_period_season ON pricing_period (season_id)');

        $this->addSql('ALTER TABLE application ADD season_id INT DEFAULT NULL');
        $this->addSql('UPDATE application SET season_id = (SELECT id FROM festival_season WHERE year = 2026)');
        $this->addSql('ALTER TABLE application CHANGE season_id season_id INT NOT NULL');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_application_season FOREIGN KEY (season_id) REFERENCES festival_season (id)');
        $this->addSql('CREATE INDEX IDX_application_season ON application (season_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_application_season');
        $this->addSql('DROP INDEX IDX_application_season ON application');
        $this->addSql('ALTER TABLE application DROP season_id');

        $this->addSql('ALTER TABLE pricing_period DROP FOREIGN KEY FK_pricing_period_season');
        $this->addSql('DROP INDEX IDX_pricing_period_season ON pricing_period');
        $this->addSql('ALTER TABLE pricing_period DROP season_id');

        $this->addSql('ALTER TABLE payment DROP refunded_amount');
        $this->addSql('DROP TABLE festival_season');
    }
}
