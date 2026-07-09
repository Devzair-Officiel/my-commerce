<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add wholesaleFormats field to product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD wholesale_formats VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN wholesale_formats');
    }
}
