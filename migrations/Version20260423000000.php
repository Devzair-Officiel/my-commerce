<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media_slider_mobile column to sliders table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sliders ADD media_slider_mobile VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sliders DROP COLUMN media_slider_mobile');
    }
}
