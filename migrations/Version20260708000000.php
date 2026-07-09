<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tasting profile bars, tasting tags, and lab analysis fields to product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD tasting_intensity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD tasting_aromatic INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD tasting_sweetness INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD tasting_fluidity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD tasting_tags VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD has_lab_analysis BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD lab_analysis TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_intensity');
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_aromatic');
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_sweetness');
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_fluidity');
        $this->addSql('ALTER TABLE product DROP COLUMN tasting_tags');
        $this->addSql('ALTER TABLE product DROP COLUMN has_lab_analysis');
        $this->addSql('ALTER TABLE product DROP COLUMN lab_analysis');
    }
}
