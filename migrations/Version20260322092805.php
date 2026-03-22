<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322092805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" ADD total_weight_grams INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product DROP brand');
        $this->addSql('ALTER TABLE product ALTER seo_title SET NOT NULL');
        $this->addSql('ALTER TABLE product ALTER seo_description SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" DROP total_weight_grams');
        $this->addSql('ALTER TABLE product ADD brand VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ALTER seo_title DROP NOT NULL');
        $this->addSql('ALTER TABLE product ALTER seo_description DROP NOT NULL');
    }
}
