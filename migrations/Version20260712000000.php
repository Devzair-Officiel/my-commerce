<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add descriptive product fields for compare view (texture, color, aromaticNotes, tastingSuggestion)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD texture_label VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD color_label VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD aromatic_notes VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD tasting_suggestion VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN texture_label');
        $this->addSql('ALTER TABLE product DROP COLUMN color_label');
        $this->addSql('ALTER TABLE product DROP COLUMN aromatic_notes');
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_suggestion');
    }
}
