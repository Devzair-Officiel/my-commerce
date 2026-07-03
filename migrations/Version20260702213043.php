<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index unique (insensible à la casse) sur honey_tasting.taster_name
 * pour garantir qu'un dégustateur ne possède qu'une seule fiche
 * et permettre la reprise d'une fiche existante.
 */
final class Version20260702213043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add case-insensitive unique index on honey_tasting.taster_name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_honey_tasting_taster_name_ci ON honey_tasting (LOWER(taster_name))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_honey_tasting_taster_name_ci');
    }
}
