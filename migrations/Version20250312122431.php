<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250312122431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE food_preference (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_food_preference (user_id INT NOT NULL, food_preference_id INT NOT NULL, INDEX IDX_28F06C7DA76ED395 (user_id), INDEX IDX_28F06C7DE2D90E57 (food_preference_id), PRIMARY KEY(user_id, food_preference_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_food_preference ADD CONSTRAINT FK_28F06C7DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_food_preference ADD CONSTRAINT FK_28F06C7DE2D90E57 FOREIGN KEY (food_preference_id) REFERENCES food_preference (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_food_preferences DROP FOREIGN KEY FK_359232E6A76ED395');
        $this->addSql('ALTER TABLE user_food_preferences DROP FOREIGN KEY FK_359232E6129CA07C');
        $this->addSql('DROP TABLE user_food_preferences');
        $this->addSql('DROP TABLE food_preferences');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_food_preferences (user_id INT NOT NULL, food_preferences_id INT NOT NULL, INDEX IDX_359232E6A76ED395 (user_id), INDEX IDX_359232E6129CA07C (food_preferences_id), PRIMARY KEY(user_id, food_preferences_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE food_preferences (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_food_preferences ADD CONSTRAINT FK_359232E6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_food_preferences ADD CONSTRAINT FK_359232E6129CA07C FOREIGN KEY (food_preferences_id) REFERENCES food_preferences (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_food_preference DROP FOREIGN KEY FK_28F06C7DA76ED395');
        $this->addSql('ALTER TABLE user_food_preference DROP FOREIGN KEY FK_28F06C7DE2D90E57');
        $this->addSql('DROP TABLE food_preference');
        $this->addSql('DROP TABLE user_food_preference');
    }
}
