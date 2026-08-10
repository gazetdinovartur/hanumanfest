<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CMS content tables for site (settings, hero, people, gallery, faq, info, reviews)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_settings (id INT AUTO_INCREMENT NOT NULL, site_name VARCHAR(255) NOT NULL, tagline VARCHAR(500) DEFAULT NULL, contacts_html LONGTEXT DEFAULT NULL, vk_url VARCHAR(255) DEFAULT NULL, telegram_url VARCHAR(255) DEFAULT NULL, notification_email VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE home_hero (id INT AUTO_INCREMENT NOT NULL, event_dates VARCHAR(255) DEFAULT NULL, title_main VARCHAR(255) DEFAULT NULL, title_secondary VARCHAR(255) DEFAULT NULL, headline VARCHAR(255) NOT NULL, image_path VARCHAR(500) DEFAULT NULL, cta_label VARCHAR(255) DEFAULT NULL, cta_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, excerpt LONGTEXT DEFAULT NULL, bio LONGTEXT DEFAULT NULL, photo_path VARCHAR(500) DEFAULT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gallery_item (id INT AUTO_INCREMENT NOT NULL, image_path VARCHAR(500) NOT NULL, caption VARCHAR(255) DEFAULT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE faq_item (id INT AUTO_INCREMENT NOT NULL, question VARCHAR(500) NOT NULL, answer LONGTEXT NOT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE info_block (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, author_name VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, photo_path VARCHAR(500) DEFAULT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_settings');
        $this->addSql('DROP TABLE home_hero');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE gallery_item');
        $this->addSql('DROP TABLE faq_item');
        $this->addSql('DROP TABLE info_block');
        $this->addSql('DROP TABLE review');
    }
}
