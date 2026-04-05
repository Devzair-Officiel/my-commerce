<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock_decremented_at to order (guard against double stock decrement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"order\" ADD COLUMN IF NOT EXISTS stock_decremented_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        $this->addSql("COMMENT ON COLUMN \"order\".stock_decremented_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"order\" DROP COLUMN IF EXISTS stock_decremented_at");
    }
}
