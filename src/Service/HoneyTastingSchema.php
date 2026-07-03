<?php

namespace App\Service;

/**
 * Source unique de vérité du schéma du carnet de dégustation :
 * liste des miels évalués et structure des sections/champs/options.
 * Utilisé côté public (formulaire) et côté admin (affichage/PDF).
 */
final class HoneyTastingSchema
{
    /**
     * @return array<string, string>
     */
    public function honeys(): array
    {
        return [
            'trefle'              => 'Trèfle',
            'agrumes'             => 'Agrumes',
            'blanc-kirghizistan'  => 'Blanc du Kirghizistan',
            'thym-cremeux'        => 'Thym crémeux',
            'manuka'              => 'Manuka',
            'jujubier-pakistan'   => 'Jujubier Pakistan',
            'euphorbe-yemen'      => 'Euphorbe Yémen',
            'daghmous-maroc'      => 'Daghmous Maroc',
            'nigelle-egypte'      => "Nigelle d'Égypte",
            'jujubier-yemen'      => 'Jujubier du Yémen',
        ];
    }

    /**
     * @return list<array{slug: string, title: string, fields: list<array{slug: string, label: string, options: list<string>, exclusive?: bool}>, textareas?: list<array{slug: string, label: string}>}>
     */
    public function sections(): array
    {
        return [
            [
                'slug'  => 'aspect-visuel',
                'title' => 'Aspect visuel',
                'fields' => [
                    ['slug' => 'couleur',        'label' => 'Couleur',        'options' => ['Blanc nacré','Ivoire','Jaune pâle','Doré','Ambré clair','Ambré','Ambré foncé','Brun','Reflets orangés','Reflets cuivrés']],
                    ['slug' => 'aspect',         'label' => 'Aspect',         'options' => ['Limpide','Légèrement trouble','Trouble','Opaque','Brillant','Mat']],
                    ['slug' => 'cristallisation','label' => 'Cristallisation','options' => ['Liquide','Débutante','Fine','Crémeuse','Moyenne','Gros cristaux','Compacte']],
                ],
            ],
            [
                'slug'  => 'texture',
                'title' => 'Texture',
                'fields' => [
                    ['slug' => 'fluidite',  'label' => 'Fluidité',  'options' => ['Très fluide','Fluide','Moyenne','Épaisse','Très épaisse','Dense']],
                    ['slug' => 'en-bouche', 'label' => 'En bouche', 'options' => ['Fondante','Veloutée','Soyeuse','Crémeuse','Onctueuse','Coulante','Granuleuse','Satinée']],
                ],
            ],
            [
                'slug'  => 'odeur',
                'title' => 'Odeur',
                'fields' => [
                    ['slug' => 'intensite', 'label' => 'Intensité', 'options' => ['Très discrète','Discrète','Moyenne','Soutenue','Très intense']],
                    ['slug' => 'famille',   'label' => 'Famille',   'options' => ['Florale','Fruitée','Herbacée','Boisée','Résineuse','Épicée','Gourmande','Maltée','Balsamique']],
                    ['slug' => 'notes',     'label' => 'Notes',     'options' => ["Fleur d'oranger",'Lavande','Acacia','Agrumes','Citron','Orange','Datte','Figue','Raisin sec','Caramel','Lait concentré','Vanille','Nougat','Beurre','Pain grillé','Mélasse','Foin','Thé','Réglisse','Poivre','Cannelle','Gingembre','Anis','Pin','Résine','Eucalyptus']],
                ],
            ],
            [
                'slug'  => 'degustation',
                'title' => 'Dégustation',
                'fields' => [
                    ['slug' => 'attaque',   'label' => 'Attaque',   'options' => ['Très douce','Douce','Progressive','Vive','Immédiatement intense']],
                    ['slug' => 'evolution', 'label' => 'Évolution', 'options' => ['Ronde','Complexe','Équilibrée','Évolutive','Linéaire','Riche','Légère']],
                    ['slug' => 'finale',    'label' => 'Finale',    'options' => ['Florale','Caramélisée','Épicée','Boisée','Poivrée','Réglissée','Résineuse','Fraîche','Légèrement amère']],
                    ['slug' => 'longueur',  'label' => 'Longueur',  'options' => ['Très courte','Courte','Moyenne','Longue','Très longue']],
                ],
            ],
            [
                'slug'  => 'equilibre',
                'title' => 'Équilibre',
                'fields' => [
                    ['slug' => 'sucrosite',        'label' => 'Sucrosité',        'options' => ['Très faible','Faible','Équilibrée','Marquée','Très marquée']],
                    ['slug' => 'acidite',          'label' => 'Acidité',          'options' => ['Aucune','Très légère','Légère','Moyenne','Marquée']],
                    ['slug' => 'amertume',         'label' => 'Amertume',         'options' => ['Aucune','Très légère','Légère','Moyenne','Marquée']],
                    ['slug' => 'intensite-globale','label' => 'Intensité globale','options' => ['★','★★','★★★','★★★★','★★★★★'], 'exclusive' => true],
                ],
            ],
            [
                'slug'  => 'accords-personnalite',
                'title' => 'Accords et personnalité',
                'fields' => [
                    ['slug' => 'accords', 'label' => 'Accords', 'options' => ['Pain','Brioche','Beurre','Yaourt','Fromage frais','Fromage affiné','Thé','Infusion','Desserts']],
                    ['slug' => 'public',  'label' => 'Public',  'options' => ['Enfant','Toute la famille','Débutant','Amateur','Connaisseur']],
                    ['slug' => 'emotion', 'label' => 'Émotion', 'options' => ['Petit-déjeuner','Dessert','Champ en fleurs','Forêt', "Soirée d'hiver", "Souvenir d'enfance"]],
                ],
                'textareas' => [
                    ['slug' => 'unique',    'label' => 'Ce qui le rend unique'],
                    ['slug' => 'remarques', 'label' => 'Remarques libres'],
                ],
            ],
        ];
    }

    /**
     * Regroupe les données brutes d'une fiche (clés `honey__section__field`
     * et notes libres `…__note`) en une structure hiérarchique prête à afficher :
     * miel → section → champ → valeurs libellées + note libre.
     *
     * @param array<string, mixed> $data
     * @return list<array{honeySlug: string, honeyName: string, sections: list<array{title: string, entries: list<array{label: string, values: list<string>, note: string, isText: bool}>}>}>
     */
    public function groupForDisplay(array $data): array
    {
        $honeys   = $this->honeys();
        $sections = $this->sections();
        $out = [];

        foreach ($honeys as $honeySlug => $honeyName) {
            $groupedSections = [];

            foreach ($sections as $section) {
                $entries = [];

                foreach ($section['fields'] as $field) {
                    $key  = $honeySlug . '__' . $section['slug'] . '__' . $field['slug'];
                    $note = trim((string) ($data[$key . '__note'] ?? ''));

                    if (!isset($data[$key]) && $note === '') {
                        continue;
                    }

                    if (isset($data[$key])) {
                        $value  = $data[$key];
                        $values = \is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
                    } else {
                        $values = [];
                    }

                    $entries[] = [
                        'label'  => $field['label'],
                        'values' => $values,
                        'note'   => $note,
                        'isText' => false,
                    ];
                }

                foreach ($section['textareas'] ?? [] as $area) {
                    $key = $honeySlug . '__' . $section['slug'] . '__' . $area['slug'];
                    if (!isset($data[$key])) {
                        continue;
                    }
                    $entries[] = [
                        'label'  => $area['label'],
                        'values' => [(string) $data[$key]],
                        'note'   => '',
                        'isText' => true,
                    ];
                }

                if ($entries) {
                    $groupedSections[] = [
                        'title'   => $section['title'],
                        'entries' => $entries,
                    ];
                }
            }

            if ($groupedSections) {
                $out[] = [
                    'honeySlug' => $honeySlug,
                    'honeyName' => $honeyName,
                    'sections'  => $groupedSections,
                ];
            }
        }

        return $out;
    }
}
