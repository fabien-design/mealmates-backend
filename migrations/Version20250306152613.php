<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250306152613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_allergene (user_id INT NOT NULL, allergene_id INT NOT NULL, INDEX IDX_93C3A701A76ED395 (user_id), INDEX IDX_93C3A7014646AB2 (allergene_id), PRIMARY KEY(user_id, allergene_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_food_preferences (user_id INT NOT NULL, food_preferences_id INT NOT NULL, INDEX IDX_359232E6A76ED395 (user_id), INDEX IDX_359232E6129CA07C (food_preferences_id), PRIMARY KEY(user_id, food_preferences_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_allergene ADD CONSTRAINT FK_93C3A701A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_allergene ADD CONSTRAINT FK_93C3A7014646AB2 FOREIGN KEY (allergene_id) REFERENCES allergene (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_food_preferences ADD CONSTRAINT FK_359232E6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_food_preferences ADD CONSTRAINT FK_359232E6129CA07C FOREIGN KEY (food_preferences_id) REFERENCES food_preferences (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_allergene DROP FOREIGN KEY FK_93C3A701A76ED395');
        $this->addSql('ALTER TABLE user_allergene DROP FOREIGN KEY FK_93C3A7014646AB2');
        $this->addSql('ALTER TABLE user_food_preferences DROP FOREIGN KEY FK_359232E6A76ED395');
        $this->addSql('ALTER TABLE user_food_preferences DROP FOREIGN KEY FK_359232E6129CA07C');
        $this->addSql('DROP TABLE user_allergene');
        $this->addSql('DROP TABLE user_food_preferences');
    }
}
