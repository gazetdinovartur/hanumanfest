<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CMS site pages (legal, kitchen, custom)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_page (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content_html LONGTEXT NOT NULL, template VARCHAR(32) NOT NULL, show_in_footer TINYINT(1) NOT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, UNIQUE INDEX uniq_site_page_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_page');
    }
}
