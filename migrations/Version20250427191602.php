<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250427191602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE saved_search_filters (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, radius INT DEFAULT NULL, product_types JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', expiration_date VARCHAR(255) DEFAULT NULL, min_price DOUBLE PRECISION DEFAULT NULL, max_price DOUBLE PRECISION DEFAULT NULL, min_seller_rating DOUBLE PRECISION DEFAULT NULL, dietary_preferences JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', INDEX IDX_80F3132BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE saved_search_filters ADD CONSTRAINT FK_80F3132BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE saved_search_filters DROP FOREIGN KEY FK_80F3132BA76ED395');
        $this->addSql('DROP TABLE saved_search_filters');
    }
}
