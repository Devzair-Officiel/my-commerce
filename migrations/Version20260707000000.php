<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index de performance sur les chemins chauds :
 * - "order".payment_reference : recherché par le webhook Stripe à chaque événement ;
 * - product.slug : recherché sur chaque page produit, et rendu UNIQUE
 *   (deux produits partageant un slug rendraient la page non déterministe).
 *
 * Avant migration, vérifier l'absence de doublons (aucun au 2026-07-07) :
 *   SELECT slug, COUNT(*) FROM product GROUP BY slug HAVING COUNT(*) > 1;
 */
final class Version20260707000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on order.payment_reference and unique index on product.slug';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_order_payment_reference ON "order" (payment_reference)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_slug ON product (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_payment_reference');
        $this->addSql('DROP INDEX uniq_product_slug');
    }
}
