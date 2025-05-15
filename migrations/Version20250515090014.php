<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515090014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search_filters DROP min_seller_rating');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search_filters ADD min_seller_rating DOUBLE PRECISION DEFAULT NULL');
    }
}
