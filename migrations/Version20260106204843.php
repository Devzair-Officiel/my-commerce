<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106204843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media DROP CONSTRAINT fk_6a2ca10c5aa1164f');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C5AA1164F FOREIGN KEY (payment_method_id) REFERENCES payment_method (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media DROP CONSTRAINT FK_6A2CA10C5AA1164F');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT fk_6a2ca10c5aa1164f FOREIGN KEY (payment_method_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
