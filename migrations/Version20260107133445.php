<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107133445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" ADD items_total_ht_cents INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD order_total_ttc_cents INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD currency VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD carrier_name_snapshot VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD carrier_price_snapshot_cents INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD payment_method_name_snapshot VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD payment_reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD payment_method_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" DROP client_name');
        $this->addSql('ALTER TABLE "order" DROP quantity');
        $this->addSql('ALTER TABLE "order" DROP order_cost_ht');
        $this->addSql('ALTER TABLE "order" DROP order_cost_ttc');
        $this->addSql('ALTER TABLE "order" DROP is_paid');
        $this->addSql('ALTER TABLE "order" DROP carrier_price');
        $this->addSql('ALTER TABLE "order" DROP carrier_name');
        $this->addSql('ALTER TABLE "order" DROP stripe_client_secret');
        $this->addSql('ALTER TABLE "order" DROP paypal_client_secret');
        $this->addSql('ALTER TABLE "order" DROP payment_method');
        $this->addSql('ALTER TABLE "order" ALTER status TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE "order" ALTER carrier_id DROP NOT NULL');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN taxe TO tax_amount_cents');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F529939821DFC797 FOREIGN KEY (carrier_id) REFERENCES carrier (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F52993985AA1164F FOREIGN KEY (payment_method_id) REFERENCES payment_method (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON "order" (user_id)');
        $this->addSql('CREATE INDEX IDX_F529939821DFC797 ON "order" (carrier_id)');
        $this->addSql('CREATE INDEX IDX_F52993985AA1164F ON "order" (payment_method_id)');
        $this->addSql('ALTER TABLE order_details DROP CONSTRAINT fk_845ca2c1bfcdf877');
        $this->addSql('ALTER TABLE order_details ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_details ADD unit_price_cents INT NOT NULL');
        $this->addSql('ALTER TABLE order_details ADD tax_amount_cents INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_details ADD line_total_cents INT NOT NULL');
        $this->addSql('ALTER TABLE order_details DROP product_solde_price');
        $this->addSql('ALTER TABLE order_details DROP product_regular_price');
        $this->addSql('ALTER TABLE order_details DROP taxe');
        $this->addSql('ALTER TABLE order_details DROP subtotal');
        $this->addSql('ALTER TABLE order_details ALTER product_description TYPE TEXT');
        $this->addSql('ALTER TABLE order_details ALTER product_description DROP NOT NULL');
        $this->addSql('ALTER TABLE order_details ADD CONSTRAINT FK_845CA2C1BFCDF877 FOREIGN KEY (my_order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment_method DROP created_at');
        $this->addSql('ALTER TABLE payment_method DROP updated_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F529939821DFC797');
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F52993985AA1164F');
        $this->addSql('DROP INDEX IDX_F5299398A76ED395');
        $this->addSql('DROP INDEX IDX_F529939821DFC797');
        $this->addSql('DROP INDEX IDX_F52993985AA1164F');
        $this->addSql('ALTER TABLE "order" ADD quantity INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD order_cost_ht INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD taxe INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD order_cost_ttc INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD is_paid BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD carrier_price INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD carrier_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD stripe_client_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD paypal_client_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD payment_method VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" DROP items_total_ht_cents');
        $this->addSql('ALTER TABLE "order" DROP tax_amount_cents');
        $this->addSql('ALTER TABLE "order" DROP order_total_ttc_cents');
        $this->addSql('ALTER TABLE "order" DROP currency');
        $this->addSql('ALTER TABLE "order" DROP carrier_price_snapshot_cents');
        $this->addSql('ALTER TABLE "order" DROP payment_method_name_snapshot');
        $this->addSql('ALTER TABLE "order" DROP payment_reference');
        $this->addSql('ALTER TABLE "order" DROP paid_at');
        $this->addSql('ALTER TABLE "order" DROP user_id');
        $this->addSql('ALTER TABLE "order" DROP payment_method_id');
        $this->addSql('ALTER TABLE "order" ALTER status TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "order" ALTER carrier_id SET NOT NULL');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN carrier_name_snapshot TO client_name');
        $this->addSql('ALTER TABLE order_details DROP CONSTRAINT FK_845CA2C1BFCDF877');
        $this->addSql('ALTER TABLE order_details ADD product_solde_price INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_details ADD product_regular_price INT NOT NULL');
        $this->addSql('ALTER TABLE order_details ADD taxe INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_details ADD subtotal INT NOT NULL');
        $this->addSql('ALTER TABLE order_details DROP product_id');
        $this->addSql('ALTER TABLE order_details DROP unit_price_cents');
        $this->addSql('ALTER TABLE order_details DROP tax_amount_cents');
        $this->addSql('ALTER TABLE order_details DROP line_total_cents');
        $this->addSql('ALTER TABLE order_details ALTER product_description TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE order_details ALTER product_description SET NOT NULL');
        $this->addSql('ALTER TABLE order_details ADD CONSTRAINT fk_845ca2c1bfcdf877 FOREIGN KEY (my_order_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payment_method ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE payment_method ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
