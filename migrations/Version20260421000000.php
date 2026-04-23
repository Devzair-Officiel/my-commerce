<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tasting_profile column to product table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD tasting_profile VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_profile');
    }
}
