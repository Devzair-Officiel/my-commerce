<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416153626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insère les pages légales obligatoires (mentions légales, CGV, politique de confidentialité)';
    }

    public function up(Schema $schema): void
    {
        $pages = [
            [
                'slug'            => 'mentions-legales',
                'title'           => 'Mentions légales',
                'seo_title'       => 'Mentions légales',
                'seo_description' => 'Mentions légales du site.',
                'content'         => '<h2>Mentions légales</h2>
<p>Conformément aux dispositions de la loi n° 2004-575 du 21 juin 2004 pour la confiance en l\'économie numérique, il est précisé aux utilisateurs du site l\'identité des différents intervenants dans le cadre de sa réalisation et de son suivi.</p>
<h3>Éditeur du site</h3>
<p><strong>Raison sociale :</strong> [Nom de votre société]<br><strong>Forme juridique :</strong> [SARL / SAS / Auto-entrepreneur]<br><strong>Siège social :</strong> [Adresse complète]<br><strong>SIRET :</strong> [Numéro SIRET]<br><strong>N° TVA :</strong> [FR…]<br><strong>Email :</strong> [contact@votre-site.fr]</p>
<h3>Directeur de la publication</h3>
<p>[Nom Prénom]</p>
<h3>Hébergeur</h3>
<p>[Nom hébergeur], [Adresse], [Site web]</p>
<h3>Propriété intellectuelle</h3>
<p>L\'ensemble du contenu de ce site est protégé par le droit d\'auteur. Toute reproduction est interdite sans autorisation préalable.</p>',
            ],
            [
                'slug'            => 'politique-de-confidentialite',
                'title'           => 'Politique de confidentialité',
                'seo_title'       => 'Politique de confidentialité',
                'seo_description' => 'Politique de confidentialité et gestion des données personnelles.',
                'content'         => '<h2>Politique de confidentialité</h2>
<p>La présente politique décrit comment nous collectons et utilisons vos données personnelles conformément au RGPD.</p>
<h3>Responsable du traitement</h3>
<p>[Nom de votre société] — [email de contact]</p>
<h3>Données collectées</h3>
<ul><li>Nom, prénom, adresse email lors de la création de compte</li><li>Adresses de livraison et de facturation</li><li>Historique de commandes</li><li>Données de navigation (cookies)</li></ul>
<h3>Finalités</h3>
<ul><li>Gestion des commandes et relation client</li><li>Envoi d\'emails transactionnels</li><li>Amélioration de l\'expérience utilisateur</li></ul>
<h3>Durée de conservation</h3>
<p>Données conservées pendant la durée de la relation commerciale, puis archivées conformément aux obligations légales.</p>
<h3>Vos droits</h3>
<p>Vous disposez d\'un droit d\'accès, de rectification, d\'effacement et d\'opposition. Contactez-nous à <strong>[email@votre-site.fr]</strong>.</p>
<h3>Cookies</h3>
<p>Notre site utilise des cookies nécessaires au fonctionnement. Vous pouvez gérer vos préférences via le bandeau de consentement.</p>',
            ],
            [
                'slug'            => 'cgv',
                'title'           => 'Conditions Générales de Vente',
                'seo_title'       => 'Conditions Générales de Vente',
                'seo_description' => 'Conditions générales de vente applicables à toutes les commandes.',
                'content'         => '<h2>Conditions Générales de Vente</h2>
<h3>Article 1 — Objet</h3>
<p>Les présentes CGV régissent les relations contractuelles entre [Nom de votre société] et tout acheteur sur le site.</p>
<h3>Article 2 — Prix</h3>
<p>Les prix sont indiqués en euros TTC. Ils peuvent être modifiés à tout moment ; les produits sont facturés au tarif en vigueur lors de la commande.</p>
<h3>Article 3 — Commande</h3>
<p>La commande est validée après confirmation du paiement. Un email de confirmation récapitulatif vous est envoyé.</p>
<h3>Article 4 — Paiement</h3>
<p>Paiement en ligne sécurisé par carte bancaire via Stripe. Le débit est effectué à la validation de la commande.</p>
<h3>Article 5 — Livraison</h3>
<p>Les produits sont expédiés à l\'adresse indiquée lors de la commande. Les délais sont donnés à titre indicatif.</p>
<h3>Article 6 — Droit de rétractation</h3>
<p>Conformément à l\'article L221-18 du Code de la consommation, vous disposez de <strong>14 jours</strong> à compter de la réception pour vous rétracter. Contactez-nous à <strong>[email@votre-site.fr]</strong>.</p>
<h3>Article 7 — Retours et remboursements</h3>
<p>Les produits retournés doivent être dans leur état d\'origine. Le remboursement est effectué sous 14 jours après réception.</p>
<h3>Article 8 — Garanties</h3>
<p>Tous nos produits bénéficient de la garantie légale de conformité (2 ans) et de la garantie contre les vices cachés.</p>
<h3>Article 9 — Litiges</h3>
<p>En cas de litige, une solution amiable sera recherchée. Plateforme européenne de résolution en ligne : <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>.</p>
<h3>Article 10 — Droit applicable</h3>
<p>Les présentes CGV sont soumises au droit français.</p>',
            ],
        ];

        foreach ($pages as $page) {
            $exists = $this->connection->fetchOne('SELECT id FROM page WHERE slug = ?', [$page['slug']]);
            if ($exists) {
                continue;
            }

            $this->addSql(
                'INSERT INTO page (title, slug, content, seo_title, seo_description, seo_noindex, is_head, is_foot) VALUES (?, ?, ?, ?, ?, false, false, false)',
                [$page['title'], $page['slug'], $page['content'], $page['seo_title'], $page['seo_description']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM page WHERE slug IN ('mentions-legales', 'politique-de-confidentialite', 'cgv')");
    }
}
