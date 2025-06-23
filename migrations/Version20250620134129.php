<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620134129 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction ADD reservation_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD reserved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD qr_code_token VARCHAR(255) DEFAULT NULL, ADD qr_code_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction DROP reservation_expires_at, DROP reserved_at, DROP qr_code_token, DROP qr_code_expires_at
        SQL);
    }
}
