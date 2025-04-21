<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250421151940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, seller_id INT NOT NULL, buyer_id INT DEFAULT NULL, address_id INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, expiry_date DATE NOT NULL, price DOUBLE PRECISION NOT NULL, dynamic_price DOUBLE PRECISION DEFAULT NULL, has_been_sold TINYINT(1) NOT NULL, is_recurring TINYINT(1) NOT NULL, INDEX IDX_29D6873E8DE820D9 (seller_id), INDEX IDX_29D6873E6C755722 (buyer_id), INDEX IDX_29D6873EF5B7AF75 (address_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE offer_allergen (offer_id INT NOT NULL, allergen_id INT NOT NULL, INDEX IDX_5CC2B8AC53C674EE (offer_id), INDEX IDX_5CC2B8AC6E775A4A (allergen_id), PRIMARY KEY(offer_id, allergen_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE offer_food_preference (offer_id INT NOT NULL, food_preference_id INT NOT NULL, INDEX IDX_2BBA852353C674EE (offer_id), INDEX IDX_2BBA8523E2D90E57 (food_preference_id), PRIMARY KEY(offer_id, food_preference_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E8DE820D9 FOREIGN KEY (seller_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E6C755722 FOREIGN KEY (buyer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873EF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE offer_allergen ADD CONSTRAINT FK_5CC2B8AC53C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer_allergen ADD CONSTRAINT FK_5CC2B8AC6E775A4A FOREIGN KEY (allergen_id) REFERENCES allergen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer_food_preference ADD CONSTRAINT FK_2BBA852353C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer_food_preference ADD CONSTRAINT FK_2BBA8523E2D90E57 FOREIGN KEY (food_preference_id) REFERENCES food_preference (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE address ADD longitude DOUBLE PRECISION NOT NULL, ADD latitude DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045F53C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)');
        $this->addSql('ALTER TABLE user CHANGE first_name first_name VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045F53C674EE');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E8DE820D9');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E6C755722');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873EF5B7AF75');
        $this->addSql('ALTER TABLE offer_allergen DROP FOREIGN KEY FK_5CC2B8AC53C674EE');
        $this->addSql('ALTER TABLE offer_allergen DROP FOREIGN KEY FK_5CC2B8AC6E775A4A');
        $this->addSql('ALTER TABLE offer_food_preference DROP FOREIGN KEY FK_2BBA852353C674EE');
        $this->addSql('ALTER TABLE offer_food_preference DROP FOREIGN KEY FK_2BBA8523E2D90E57');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE offer_allergen');
        $this->addSql('DROP TABLE offer_food_preference');
        $this->addSql('ALTER TABLE address DROP longitude, DROP latitude');
        $this->addSql('ALTER TABLE user CHANGE first_name first_name VARCHAR(50) DEFAULT NULL');
    }
}
