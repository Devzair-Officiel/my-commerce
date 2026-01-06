<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106201159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wishlist DROP CONSTRAINT fk_9ce12a319395c3f3');
        $this->addSql('ALTER TABLE wishlist ALTER customer_id SET NOT NULL');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A319395C3F3 FOREIGN KEY (customer_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wishlist DROP CONSTRAINT FK_9CE12A319395C3F3');
        $this->addSql('ALTER TABLE wishlist ALTER customer_id DROP NOT NULL');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT fk_9ce12a319395c3f3 FOREIGN KEY (customer_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
