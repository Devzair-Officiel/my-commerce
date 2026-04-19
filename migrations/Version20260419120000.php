<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create faq table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE faq (
            id SERIAL PRIMARY KEY,
            question VARCHAR(255) NOT NULL,
            answer TEXT NOT NULL,
            position INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT true
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE faq');
    }
}
