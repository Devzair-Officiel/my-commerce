<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231050018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media ADD setting_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10CEE35BD72 FOREIGN KEY (setting_id) REFERENCES setting (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2CA10CEE35BD72 ON media (setting_id)');
        $this->addSql('ALTER TABLE setting DROP logo');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media DROP CONSTRAINT FK_6A2CA10CEE35BD72');
        $this->addSql('DROP INDEX UNIQ_6A2CA10CEE35BD72');
        $this->addSql('ALTER TABLE media DROP setting_id');
        $this->addSql('ALTER TABLE setting ADD logo VARCHAR(255) NOT NULL');
    }
}
