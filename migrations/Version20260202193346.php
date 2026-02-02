<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202193346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE outbox_events (id VARCHAR(36) NOT NULL, aggregate_id VARCHAR(255) NOT NULL, aggregate_type VARCHAR(100) NOT NULL, event_type VARCHAR(100) NOT NULL, payload JSON NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, retry_count INT DEFAULT 0 NOT NULL, error_message TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_outbox_status_occurred ON outbox_events (status, occurred_at)');
        $this->addSql('CREATE INDEX idx_outbox_aggregate ON outbox_events (aggregate_id, aggregate_type)');
        $this->addSql('CREATE INDEX idx_outbox_event_type ON outbox_events (event_type)');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, roles JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reset_token VARCHAR(255) DEFAULT NULL, reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE INDEX idx_users_email ON users (email)');
        $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');
        $this->addSql('CREATE INDEX idx_users_reset_token ON users (reset_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE outbox_events');
        $this->addSql('DROP TABLE users');
    }
}
