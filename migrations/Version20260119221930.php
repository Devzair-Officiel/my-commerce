<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260119221930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_related (product_id INT NOT NULL, related_product_id INT NOT NULL, PRIMARY KEY (product_id, related_product_id))');
        $this->addSql('CREATE INDEX IDX_B18E6B204584665A ON product_related (product_id)');
        $this->addSql('CREATE INDEX IDX_B18E6B20CF496EEA ON product_related (related_product_id)');
        $this->addSql('ALTER TABLE product_related ADD CONSTRAINT FK_B18E6B204584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_related ADD CONSTRAINT FK_B18E6B20CF496EEA FOREIGN KEY (related_product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT fk_d34a04ada761ff2d');
        $this->addSql('DROP INDEX idx_d34a04ada761ff2d');
        $this->addSql('ALTER TABLE product DROP related_products_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_related DROP CONSTRAINT FK_B18E6B204584665A');
        $this->addSql('ALTER TABLE product_related DROP CONSTRAINT FK_B18E6B20CF496EEA');
        $this->addSql('DROP TABLE product_related');
        $this->addSql('ALTER TABLE product ADD related_products_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT fk_d34a04ada761ff2d FOREIGN KEY (related_products_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d34a04ada761ff2d ON product (related_products_id)');
    }
}
