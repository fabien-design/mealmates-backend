<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620123228 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction CHANGE stripe_session_id stripe_session_id VARCHAR(255) DEFAULT NULL, CHANGE stripe_payment_intent_id stripe_payment_intent_id VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction CHANGE stripe_session_id stripe_session_id VARCHAR(255) NOT NULL, CHANGE stripe_payment_intent_id stripe_payment_intent_id VARCHAR(255) NOT NULL
        SQL);
    }
}
