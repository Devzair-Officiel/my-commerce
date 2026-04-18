<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hero_poster_filename to setting table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE setting ADD hero_poster_filename VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE setting DROP COLUMN hero_poster_filename");
    }
}
