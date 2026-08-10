<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend CMS hero/settings for full WP landing parity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_hero ADD about_html LONGTEXT DEFAULT NULL, ADD promo_video_left VARCHAR(500) DEFAULT NULL, ADD promo_video_right VARCHAR(500) DEFAULT NULL, CHANGE title_secondary title_secondary VARCHAR(1000) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_settings ADD logo_path VARCHAR(500) DEFAULT NULL, ADD footer_background_path VARCHAR(500) DEFAULT NULL, ADD company_info LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_hero DROP about_html, DROP promo_video_left, DROP promo_video_right, CHANGE title_secondary title_secondary VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_settings DROP logo_path, DROP footer_background_path, DROP company_info');
    }
}
