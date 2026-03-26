<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326093243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog ADD seo_title VARCHAR(70) DEFAULT NULL');
        $this->addSql('ALTER TABLE blog ADD seo_description VARCHAR(170) DEFAULT NULL');
        $this->addSql('ALTER TABLE blog ADD seo_noindex BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE blog ADD seo_og_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE blog ADD seo_canonical_override VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD intro TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD seo_title VARCHAR(70) DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD seo_description VARCHAR(170) DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD seo_noindex BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE category ADD seo_og_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD seo_canonical_override VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD seo_title VARCHAR(70) DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD seo_description VARCHAR(170) DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD seo_noindex BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE page ADD seo_og_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD seo_canonical_override VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog DROP seo_title');
        $this->addSql('ALTER TABLE blog DROP seo_description');
        $this->addSql('ALTER TABLE blog DROP seo_noindex');
        $this->addSql('ALTER TABLE blog DROP seo_og_image');
        $this->addSql('ALTER TABLE blog DROP seo_canonical_override');
        $this->addSql('ALTER TABLE category DROP intro');
        $this->addSql('ALTER TABLE category DROP seo_title');
        $this->addSql('ALTER TABLE category DROP seo_description');
        $this->addSql('ALTER TABLE category DROP seo_noindex');
        $this->addSql('ALTER TABLE category DROP seo_og_image');
        $this->addSql('ALTER TABLE category DROP seo_canonical_override');
        $this->addSql('ALTER TABLE page DROP seo_title');
        $this->addSql('ALTER TABLE page DROP seo_description');
        $this->addSql('ALTER TABLE page DROP seo_noindex');
        $this->addSql('ALTER TABLE page DROP seo_og_image');
        $this->addSql('ALTER TABLE page DROP seo_canonical_override');
    }
}
