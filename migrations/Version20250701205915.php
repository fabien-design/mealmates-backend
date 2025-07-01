<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250701205915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE badge (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, threshold INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_badge (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, badge_id INT NOT NULL, awarded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', current_progress INT DEFAULT NULL, INDEX IDX_1C32B345A76ED395 (user_id), INDEX IDX_1C32B345F7A2C2FC (badge_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_progress (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, progress_type VARCHAR(50) NOT NULL, current_value INT NOT NULL, last_updated DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_C28C1646A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_progress ADD CONSTRAINT FK_C28C1646A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD credits INT NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345F7A2C2FC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_progress DROP FOREIGN KEY FK_C28C1646A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE badge
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_badge
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_progress
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP credits
        SQL);
    }
}
