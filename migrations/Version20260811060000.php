<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Home highlights CMS, site settings pricing blocks, kitchen page videos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE home_highlight (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, column_side VARCHAR(16) NOT NULL, style VARCHAR(16) NOT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE site_settings ADD discounts_html LONGTEXT DEFAULT NULL, ADD tent_note_html LONGTEXT DEFAULT NULL, ADD cooperation_cta_html LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_page ADD kitchen_video_1 VARCHAR(500) DEFAULT NULL, ADD kitchen_video_2 VARCHAR(500) DEFAULT NULL, ADD kitchen_video_3 VARCHAR(500) DEFAULT NULL, ADD kitchen_video_4 VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE home_highlight');
        $this->addSql('ALTER TABLE site_settings DROP discounts_html, DROP tent_note_html, DROP cooperation_cta_html');
        $this->addSql('ALTER TABLE site_page DROP kitchen_video_1, DROP kitchen_video_2, DROP kitchen_video_3, DROP kitchen_video_4');
    }
}
