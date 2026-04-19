<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hero_title, hero_button_text, hero_button_url to setting table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE setting ADD hero_title VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE setting ADD hero_button_text VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE setting ADD hero_button_url VARCHAR(512) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE setting DROP COLUMN hero_title");
        $this->addSql("ALTER TABLE setting DROP COLUMN hero_button_text");
        $this->addSql("ALTER TABLE setting DROP COLUMN hero_button_url");
    }
}
