<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Review media attachments (photos/videos in reviews)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE review_media (id INT AUTO_INCREMENT NOT NULL, review_id INT NOT NULL, kind VARCHAR(16) NOT NULL, path VARCHAR(500) NOT NULL, sort_order INT NOT NULL, published TINYINT(1) NOT NULL, INDEX IDX_REVIEW_MEDIA_REVIEW (review_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review_media ADD CONSTRAINT FK_REVIEW_MEDIA_REVIEW FOREIGN KEY (review_id) REFERENCES review (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_media DROP FOREIGN KEY FK_REVIEW_MEDIA_REVIEW');
        $this->addSql('DROP TABLE review_media');
    }
}
