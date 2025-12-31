<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231001814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE setting ADD facebook_link VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE setting ADD insta_link VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE setting ADD youtube_link VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE setting ADD copyright VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE setting DROP facebook_link');
        $this->addSql('ALTER TABLE setting DROP insta_link');
        $this->addSql('ALTER TABLE setting DROP youtube_link');
        $this->addSql('ALTER TABLE setting DROP copyright');
    }
}
