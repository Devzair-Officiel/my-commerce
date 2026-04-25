<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute is_wholesale sur la table faq pour distinguer FAQ grossiste / particulier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE faq ADD is_wholesale BOOLEAN NOT NULL DEFAULT FALSE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE faq DROP COLUMN is_wholesale");
    }
}
