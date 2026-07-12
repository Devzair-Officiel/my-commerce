<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data migration : renseigne texture_label, color_label, aromatic_notes et
 * tasting_suggestion pour les 10 miels du catalogue Nidemiel.
 * Aucune autre colonne n'est modifiée. Update par slug (jamais par id).
 */
final class Version20260712120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed descriptive fields (texture, color, aromatic notes, tasting suggestion) for the 10 Nidemiel honeys';
    }

    /**
     * @return list<array{slug: string, texture: string, color: string, notes: string, suggestion: string}>
     */
    private function honeys(): array
    {
        return [
            [
                'slug'       => 'miel-de-jujubier-sidr-du-yemen',
                'texture'    => 'Dense, épaisse et fondante',
                'color'      => 'Doré soutenu à ambré',
                'notes'      => 'Caramel doux, datte, fleur miellée, finale longue',
                'suggestion' => 'À savourer lentement à la cuillère, sur une brioche, un pain complet, avec un yaourt nature ou dans une infusion tiède.',
            ],
            [
                'slug'       => 'miel-de-jujubier-du-pakistan',
                'texture'    => 'Fluide, coulante et soyeuse',
                'color'      => 'Doré à ambré',
                'notes'      => 'Fleur miellée, caramel léger, touche fruitée, finale douce',
                'suggestion' => 'À déguster à la cuillère, dans un thé doux, une infusion tiède, sur du pain ou avec un yaourt nature.',
            ],
            [
                'slug'       => 'miel-deuphorbe-du-yemen',
                'texture'    => 'Épaisse, crémeuse et dense',
                'color'      => 'Blanc nacré à jaune pâle',
                'notes'      => 'Lait concentré, nougat, poivre, finale chaude',
                'suggestion' => 'À savourer en petite quantité à la cuillère, sur une brioche peu sucrée, avec du pain complet ou dans une infusion tiède.',
            ],
            [
                'slug'       => 'miel-de-daghmous-du-maroc',
                'texture'    => 'Fluide, coulante et légèrement soyeuse',
                'color'      => 'Ambré à ambré foncé',
                'notes'      => 'Poivre, épices, chaleur végétale, finale très longue',
                'suggestion' => 'À savourer en petite quantité à la cuillère, dans une infusion tiède, avec du pain complet ou en touche fine sur un fromage affiné.',
            ],
            [
                'slug'       => 'miel-nigelle-egypte',
                'texture'    => 'Fluide, veloutée et légèrement onctueuse',
                'color'      => 'Ambré foncé à brun',
                'notes'      => 'Réglisse, anis, épices douces, finale longue',
                'suggestion' => 'À savourer à la cuillère, dans une infusion tiède, avec un yaourt nature, un fromage frais ou sur du pain complet.',
            ],
            [
                'slug'       => 'miel-agrumes-egypte',
                'texture'    => 'Fluide, coulante et légèrement sirupeuse',
                'color'      => 'Doré à ambré clair',
                'notes'      => 'Floral, agrumes, résine légère, nuance épicée',
                'suggestion' => 'Très agréable dans un thé, une infusion tiède, un yaourt nature, sur des crêpes ou pour parfumer un dessert simple.',
            ],
            [
                'slug'       => 'miel-blanc-trefle-nouvelle-zelande',
                'texture'    => 'Crémeuse, fondante et veloutée',
                'color'      => 'Blanc nacré à ivoire',
                'notes'      => 'Douceur lactée, vanille légère, caramel blond, finale délicate',
                'suggestion' => 'Idéal sur une brioche, une tartine beurrée, des crêpes, un yaourt nature ou simplement à la cuillère.',
            ],
            [
                'slug'       => 'miel-blanc-du-kirghizistan',
                'texture'    => 'Crémeuse, fondante et légèrement mousseuse',
                'color'      => 'Blanc nacré à ivoire',
                'notes'      => 'Douceur lactée, nuance florale, touche maltée, finale délicatement poivrée',
                'suggestion' => 'À savourer sur une brioche, une tartine, des crêpes, un yaourt nature ou à la cuillère pour apprécier sa texture fondante.',
            ],
            [
                'slug'       => 'miel-de-thym-cremeux-de-nouvelle-zelande',
                'texture'    => 'Crémeuse, fondante et veloutée',
                'color'      => 'Doré à ambré, avec de légers reflets orangés',
                'notes'      => 'Douceur florale, thym, notes herbacées, nuance résineuse, finale légèrement caramélisée',
                'suggestion' => 'À savourer à la cuillère, sur du pain grillé, une brioche peu sucrée, un yaourt nature ou avec un fromage frais.',
            ],
            [
                'slug'       => 'miel-de-manuka-de-nouvelle-zelande-mgo845-npa20',
                'texture'    => 'Dense, compacte et légèrement granuleuse',
                'color'      => 'Ambré foncé à brun',
                'notes'      => 'Boisé, résineux, épices douces, légère amertume, finale persistante',
                'suggestion' => 'À savourer lentement à la petite cuillère, sur un pain complet, un pain d’épices peu sucré ou avec une infusion tiède.',
            ],
        ];
    }

    public function up(Schema $schema): void
    {
        foreach ($this->honeys() as $h) {
            $this->addSql(
                'UPDATE product
                    SET texture_label      = :texture,
                        color_label        = :color,
                        aromatic_notes     = :notes,
                        tasting_suggestion = :suggestion
                  WHERE slug = :slug',
                [
                    'texture'    => $h['texture'],
                    'color'      => $h['color'],
                    'notes'      => $h['notes'],
                    'suggestion' => $h['suggestion'],
                    'slug'       => $h['slug'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $slugs = array_map(fn(array $h) => $h['slug'], $this->honeys());
        $placeholders = implode(', ', array_fill(0, \count($slugs), '?'));

        $this->addSql(
            'UPDATE product
                SET texture_label      = NULL,
                    color_label        = NULL,
                    aromatic_notes     = NULL,
                    tasting_suggestion = NULL
              WHERE slug IN ('.$placeholders.')',
            $slugs
        );
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
