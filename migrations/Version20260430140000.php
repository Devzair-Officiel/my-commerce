<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_log table to track changes on Order, Product, User, Review and Address entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
                id          SERIAL      PRIMARY KEY,
                entity_class VARCHAR(50) NOT NULL,
                entity_id   INT         NOT NULL,
                action      VARCHAR(10) NOT NULL,
                changes     JSON        NOT NULL,
                performed_by VARCHAR(255)         DEFAULT NULL,
                performed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                ip_address  VARCHAR(45)          DEFAULT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_audit_entity ON audit_log (entity_class, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_at     ON audit_log (performed_at)');
        $this->addSql('CREATE INDEX idx_audit_by     ON audit_log (performed_by)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
