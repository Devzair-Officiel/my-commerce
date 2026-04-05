<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Invoice entity + seller fields on Setting (ownerName, siret, legalMentions) + invoice relation on Order';
    }

    public function up(Schema $schema): void
    {
        // Champs micro-entrepreneur sur setting
        $this->addSql("ALTER TABLE setting ADD owner_name VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE setting ADD siret VARCHAR(20) DEFAULT NULL");
        $this->addSql("ALTER TABLE setting ADD legal_mentions VARCHAR(255) DEFAULT NULL");

        // Table invoice
        $this->addSql("CREATE TABLE invoice (
            id SERIAL NOT NULL,
            customer_order_id INT NOT NULL,
            invoice_number VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            seller_snapshot JSON DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            issued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");

        $this->addSql("CREATE UNIQUE INDEX UNIQ_906517442DA68207 ON invoice (invoice_number)");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_90651744A57935AD ON invoice (customer_order_id)");
        $this->addSql("ALTER TABLE invoice ADD CONSTRAINT FK_90651744A57935AD FOREIGN KEY (customer_order_id) REFERENCES \"order\" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");

        $this->addSql("COMMENT ON COLUMN invoice.issued_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN invoice.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN invoice.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE invoice DROP CONSTRAINT FK_90651744A57935AD");
        $this->addSql("DROP TABLE invoice");
        $this->addSql("ALTER TABLE setting DROP owner_name");
        $this->addSql("ALTER TABLE setting DROP siret");
        $this->addSql("ALTER TABLE setting DROP legal_mentions");
    }
}
