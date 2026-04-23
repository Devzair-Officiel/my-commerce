<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add accroche column to product table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD accroche VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN accroche');
    }
}
