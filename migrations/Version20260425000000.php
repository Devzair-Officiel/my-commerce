<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passer product.description de VARCHAR(255) à TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER COLUMN description TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER COLUMN description TYPE VARCHAR(255)');
    }
}
