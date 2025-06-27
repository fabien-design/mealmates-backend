<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250624170654 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, reviewer_id INT NOT NULL, reviewed_id INT NOT NULL, transaction_id INT NOT NULL, product_quality_rating DOUBLE PRECISION DEFAULT NULL, appointment_respect_rating DOUBLE PRECISION DEFAULT NULL, friendliness_rating DOUBLE PRECISION DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', status VARCHAR(20) NOT NULL, moderated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', moderation_comment VARCHAR(255) DEFAULT NULL, INDEX IDX_794381C670574616 (reviewer_id), INDEX IDX_794381C65254E55 (reviewed_id), INDEX IDX_794381C62FC0CB0F (transaction_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C670574616 FOREIGN KEY (reviewer_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C65254E55 FOREIGN KEY (reviewed_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C62FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD average_rating DOUBLE PRECISION DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP FOREIGN KEY FK_794381C670574616
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP FOREIGN KEY FK_794381C65254E55
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP FOREIGN KEY FK_794381C62FC0CB0F
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE review
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP average_rating
        SQL);
    }
}
