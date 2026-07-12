<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Fournit le contenu éditorial du Guide Sensoriel Nidemiel.
 *
 * Les données sont statiques — pas de dépendance base de données.
 * Toute mise à jour éditoriale se fait ici (une seule source de vérité).
 */
final class GuideSensorielDataProvider
{
    /**
     * @return list<array{id: string, label: string, num: string}>
     */
    public function getToc(): array
    {
        $items = [
            ['introduction', 'Introduction'],
            ['degustation',  'Comment déguster un miel'],
            ['erreurs',      'Les erreurs à éviter'],
            ['criteres',     'Les critères de dégustation'],
            ['textures',     'Les textures'],
            ['couleurs',     'Les couleurs'],
            ['familles',     'Les familles aromatiques'],
            ['roue',         'La roue des arômes'],
            ['sensations',   'Les sensations en bouche'],
            ['accords',      'Les accords gourmands'],
            ['analyses',     'Lire une analyse de miel'],
            ['miels',        'Les 10 miels Nidemiel'],
            ['faq',          'FAQ'],
        ];

        return array_map(
            static fn(array $t, int $i): array => [
                'id'    => $t[0],
                'label' => $t[1],
                'num'   => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            ],
            $items,
            array_keys($items),
        );
    }

    /**
     * @return list<string>
     */
    public function getLearn(): array
    {
        return [
            'La méthode pour déguster un miel comme un pro — sans matériel.',
            'Lire une texture, une couleur et une intensité en un coup d’œil.',
            'Le vocabulaire des dix familles aromatiques et de la roue des arômes.',
            'Les accords gourmands qui révèlent chaque miel.',
            'Le profil détaillé des dix miels de la sélection Nidemiel.',
        ];
    }

    /**
     * @return list<array{num: string, title: string, text: string, icon: string}>
     */
    public function getSteps(): array
    {
        $steps = [
            [
                'title' => 'Température idéale',
                'text'  => 'Sortez le miel à température ambiante, autour de 20 °C : le froid durcit la texture et referme les arômes, alors qu’à température ambiante ils s’expriment pleinement.',
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="12" cy="12" r="8"/><line x1="12" y1="12" x2="12" y2="7"/></svg>',
            ],
            [
                'title' => 'Lumière du jour',
                'text'  => 'Observez la couleur et la limpidité à la lumière naturelle, pas sous un néon.',
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>',
            ],
            [
                'title' => 'Ordre de dégustation',
                'text'  => 'Allez du plus doux au plus intense — avec notre sélection : Trèfle → Blanc du Kirghizistan → Agrumes → Jujubier Pakistan → Thym → Nigelle → Daghmous → Euphorbe → Jujubier Yémen → Manuka.',
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><line x1="5" y1="8" x2="19" y2="8"/><line x1="5" y1="12" x2="15" y2="12"/><line x1="5" y1="16" x2="11" y2="16"/></svg>',
            ],
            [
                'title' => 'Nettoyer le palais',
                'text'  => 'Une gorgée d’eau tiède ou un peu de pain neutre entre chaque miel.',
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M7 4h10l-1.5 16h-7z"/><line x1="7" y1="9" x2="17" y2="9"/></svg>',
            ],
            [
                'title' => 'Cinq miels maximum',
                'text'  => 'Au-delà, le palais sature et ne distingue plus les nuances.',
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/></svg>',
            ],
            [
                'title' => 'Prendre des notes',
                'text'  => 'Notez texture, couleur et arômes : la mémoire du goût se construit.',
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><line x1="9" y1="10" x2="15" y2="10"/><line x1="9" y1="14" x2="15" y2="14"/></svg>',
            ],
        ];

        return array_map(
            static fn(array $s, int $i): array => [
                ...$s,
                'num' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            ],
            $steps,
            array_keys($steps),
        );
    }

    /**
     * @return list<array{num: string, title: string, text: string}>
     */
    public function getErreurs(): array
    {
        $erreurs = [
            ['Goûter trop de miels à la suite',      'Le palais sature vite ; limitez-vous à cinq références par séance.'],
            ['Déguster un miel froid',                'Le froid masque les arômes et fige la texture. Patientez avant de goûter.'],
            ['Manger un aliment fort juste avant',    'Café, agrumes ou épices faussent complètement la perception.'],
            ['Juger uniquement la couleur',           'Un miel clair peut être plus intense qu’un miel foncé. La couleur n’est qu’un indice.'],
            ['Confondre douceur et qualité',          'La douceur est un profil, pas un gage de qualité. Un miel de caractère a sa place.'],
            ['Utiliser un vocabulaire flou',          'Cherchez des repères précis : floral, boisé, malté, réglissé…'],
        ];

        return array_map(
            static fn(array $e, int $i): array => [
                'num'   => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'title' => $e[0],
                'text'  => $e[1],
            ],
            $erreurs,
            array_keys($erreurs),
        );
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    public function getCriteres(): array
    {
        return [
            ['title' => 'Texture',    'text' => 'Du plus fluide au plus crémeux, elle raconte la cristallisation — et annonce comment le miel se comportera en bouche.'],
            ['title' => 'Couleur',    'text' => 'Du blanc nacré au brun foncé — un indice d’intensité utile pour anticiper un profil, jamais une preuve de qualité.'],
            ['title' => 'Intensité',  'text' => 'La puissance aromatique, de discrète à franchement affirmée : à choisir selon l’envie du moment.'],
            ['title' => 'Arômes',     'text' => 'Les familles et descripteurs qui signent le miel — floral, gourmand, épicé, résineux…'],
            ['title' => 'Attaque',    'text' => 'La toute première impression en bouche : douce et progressive, ou franche et immédiate.'],
            ['title' => 'Évolution',  'text' => 'Comment les arômes se développent après l’attaque — certains miels s’ouvrent, d’autres restent constants.'],
            ['title' => 'Finale',     'text' => 'Ce qui domine juste avant que le goût ne s’efface : poivré, mentholé, caramélisé…'],
            ['title' => 'Longueur',   'text' => 'La persistance des arômes une fois le miel avalé, de courte à très longue.'],
            ['title' => 'Équilibre',  'text' => 'Le rapport entre douceur, acidité et amertume — ce qui rend un miel harmonieux ou franchement marqué.'],
            ['title' => 'Accords',    'text' => 'Les aliments qui révèlent ou prolongent le miel, du pain grillé au fromage affiné.'],
        ];
    }

    /**
     * @return list<array{name: string, level: int, text: string}>
     */
    public function getTextures(): array
    {
        return [
            ['name' => 'Très fluide',    'level' => 12, 'text' => 'Coule immédiatement, presque liquide — comme le Daghmous du Maroc.'],
            ['name' => 'Fluide',         'level' => 24, 'text' => 'S’écoule sans effort à la cuillère, fluide comme l’Agrumes.'],
            ['name' => 'Sirupeuse',      'level' => 40, 'text' => 'Épaisse et brillante, elle s’étire, légèrement sirupeuse comme l’Agrumes d’Égypte.'],
            ['name' => 'Épaisse',        'level' => 62, 'text' => 'Se tient, tombe lentement du bord.'],
            ['name' => 'Très épaisse',   'level' => 84, 'text' => 'Quasi solide, presque à couper — dense comme le Manuka.'],
            ['name' => 'Crémeuse',       'level' => 55, 'text' => 'Cristallisation fine, tartinable, crémeuse comme le Trèfle.'],
            ['name' => 'Très crémeuse',  'level' => 72, 'text' => 'Dense et onctueuse, souple sous la cuillère.'],
            ['name' => 'Fondante',       'level' => 46, 'text' => 'Fond en bouche, soyeuse, fondante comme le Blanc du Kirghizistan.'],
            ['name' => 'Mousseuse',      'level' => 30, 'text' => 'Aérée et légère, comme fouettée.'],
        ];
    }

    /**
     * @return list<array{name: string, hex: string}>
     */
    public function getCouleurs(): array
    {
        return [
            ['name' => 'Blanc nacré',    'hex' => '#F5EFE3'],
            ['name' => 'Ivoire',         'hex' => '#F1E6C9'],
            ['name' => 'Jaune paille',   'hex' => '#EBD48F'],
            ['name' => 'Doré',           'hex' => '#E4B84A'],
            ['name' => 'Ambré clair',    'hex' => '#D99C2B'],
            ['name' => 'Ambré',          'hex' => '#C77E1E'],
            ['name' => 'Ambré foncé',    'hex' => '#A85F16'],
            ['name' => 'Brun',           'hex' => '#7E3F12'],
            ['name' => 'Brun foncé',     'hex' => '#4E260C'],
        ];
    }

    /**
     * @return list<array{name: string, desc: string, hex: string}>
     */
    public function getFamilles(): array
    {
        return [
            ['name' => 'Florale',              'desc' => 'fleurs blanches, fleur miellée, jasmin',        'hex' => '#F3E6C4'],
            ['name' => 'Fruitée',              'desc' => 'orange, datte, figue, fruits confits',           'hex' => '#EDD7A2'],
            ['name' => 'Gourmande',            'desc' => 'lait concentré, caramel blond, nougat, vanille', 'hex' => '#E7C880'],
            ['name' => 'Herbacée',             'desc' => 'foin, thé, herbe sèche',                          'hex' => '#E0B65E'],
            ['name' => 'Épicée',               'desc' => 'poivre, gingembre, épices douces',                'hex' => '#D8A440'],
            ['name' => 'Résineuse',            'desc' => 'sève, pin, résine',                               'hex' => '#CD9130'],
            ['name' => 'Boisée',               'desc' => 'bois sec, écorce, finale chaude',                 'hex' => '#C07E22'],
            ['name' => 'Anisée / réglissée',   'desc' => 'anis, réglisse',                                  'hex' => '#AC6A1E'],
            ['name' => 'Maltée',               'desc' => 'céréales grillées, malt',                         'hex' => '#94571B'],
            ['name' => 'Cireuse',              'desc' => "cire d'abeille, propolis",                        'hex' => '#7A4617'],
        ];
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    public function getSensations(): array
    {
        return [
            ['title' => 'Attaque douce',          'text' => 'Le miel se pose en bouche, sans heurt.'],
            ['title' => 'Attaque vive',           'text' => 'Il se fait sentir immédiatement, franc.'],
            ['title' => 'Sucrosité modulable',    'text' => 'De faible à soutenue selon le miel.'],
            ['title' => 'Rondeur',                'text' => 'Une sensation pleine, presque veloutée.'],
            ['title' => 'Chaleur',                'text' => 'Une impression réconfortante, épicée.'],
            ['title' => 'Fraîcheur',              'text' => 'Des notes mentholées ou herbacées, légères.'],
            ['title' => 'Finale longue',          'text' => 'Les arômes persistent après l’avoir avalé.'],
            ['title' => 'Finale courte',          'text' => 'Le goût s’efface vite et proprement.'],
            ['title' => 'Légère amertume',        'text' => 'Une pointe qui équilibre le sucre.'],
            ['title' => 'Sensation enveloppante', 'text' => 'Le miel tapisse le palais tout entier.'],
            ['title' => 'Texture fondante',       'text' => 'Il se dissout doucement, sans grain.'],
        ];
    }

    /**
     * @return list<string>
     */
    public function getAccords(): array
    {
        return [
            'Brioche', 'Pain grillé', 'Beurre', 'Yaourt nature', 'Fromage frais',
            'Fromage affiné', 'Infusion', 'Thé', 'Crêpes', 'Desserts simples',
        ];
    }

    /**
     * Miels de la sélection Nidemiel.
     *
     * @return list<array{
     *     name: string, origin: string, texture: string, intensity: int,
     *     colorName: string, hex: string, notes: list<string>, sig: string,
     *     analysis: list<string>, url: string, image: string, alt: string
     * }>
     */
    public function getMiels(): array
    {
        return [
            [
                'name'      => 'Miel de Jujubier — Sidr du Yémen',
                'origin'    => 'YÉMEN',
                'texture'   => 'Dense, épaisse, fondante',
                'intensity' => 5,
                'colorName' => 'Doré soutenu à ambré',
                'hex'       => '#D99C2B',
                'notes'     => ['Caramel doux', 'Datte', 'Fleur miellée', 'Finale longue'],
                'sig'       => 'Le jujubier le plus dense et profond de la sélection Nidemiel.',
                'analysis'  => ['Humidité 14,2 %', 'pH 6,45', 'Conductivité 1,14 mS/cm', 'Pollen de jujubier 64 %'],
                'url'       => '/miels/miel-de-jujubier-sidr-du-yemen',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel de jujubier Sidr du Yémen en cuillère, texture dense et ambrée',
            ],
            [
                'name'      => 'Miel de Thym crémeux de Nouvelle-Zélande',
                'origin'    => 'NOUVELLE-ZÉLANDE',
                'texture'   => 'Crémeuse',
                'intensity' => 4,
                'colorName' => 'Doré à ambré',
                'hex'       => '#D99C2B',
                'notes'     => ['Floral', 'Thym', 'Herbacé', 'Résine', 'Caramel léger'],
                'sig'       => 'Un miel crémeux aromatique, entre douceur florale et finale herbacée.',
                'analysis'  => ['Humidité 17,6 %', 'Conductivité 0,30 mS/cm', 'Pollen de thym 23 %'],
                'url'       => '/miels/miel-de-thym-cremeux-de-nouvelle-zelande',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel de thym crémeux de Nouvelle-Zélande, texture onctueuse dorée',
            ],
            [
                'name'      => 'Miel d’Euphorbe du Yémen',
                'origin'    => 'YÉMEN',
                'texture'   => 'Épaisse, crémeuse, dense',
                'intensity' => 5,
                'colorName' => 'Blanc nacré à jaune pâle',
                'hex'       => '#F5EFE3',
                'notes'     => ['Lait concentré', 'Nougat', 'Poivre', 'Finale chaude'],
                'sig'       => 'Un miel clair et crémeux, gourmand au départ, puis longuement poivré.',
                'analysis'  => [],
                'url'       => '/miels/miel-deuphorbe-du-yemen',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel d’Euphorbe du Yémen, texture dense et claire',
            ],
            [
                'name'      => 'Miel de Daghmous du Maroc',
                'origin'    => 'MAROC',
                'texture'   => 'Fluide',
                'intensity' => 5,
                'colorName' => 'Ambré à ambré foncé',
                'hex'       => '#A85F16',
                'notes'     => ['Poivre', 'Épices', 'Chaleur végétale', 'Finale très longue'],
                'sig'       => 'Le miel marocain de caractère, fluide, poivré et très long en bouche.',
                'analysis'  => ['Humidité 16,5 %', 'Conductivité 0,37 mS/cm', 'Pollens d’euphorbes 23 %', 'Miel de fleurs'],
                'url'       => '/miels/miel-de-daghmous-du-maroc',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel de Daghmous du Maroc, texture fluide ambrée',
            ],
            [
                'name'      => 'Miel de Jujubier du Pakistan',
                'origin'    => 'PAKISTAN',
                'texture'   => 'Fluide',
                'intensity' => 4,
                'colorName' => 'Doré à ambré',
                'hex'       => '#D99C2B',
                'notes'     => ['Fleur miellée', 'Caramel léger', 'Touche fruitée', 'Finale douce'],
                'sig'       => 'Le jujubier le plus fluide et accessible de la sélection.',
                'analysis'  => ['Humidité 15,2 %', 'pH 6,36', 'Conductivité 1,08 mS/cm', 'Pollen de jujubier 16 %'],
                'url'       => '/miels/miel-de-jujubier-du-pakistan',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel de Jujubier du Pakistan, texture fluide dorée',
            ],
            [
                'name'      => 'Miel de Nigelle d’Égypte',
                'origin'    => 'ÉGYPTE',
                'texture'   => 'Fluide, veloutée',
                'intensity' => 4,
                'colorName' => 'Ambré foncé à brun',
                'hex'       => '#7E3F12',
                'notes'     => ['Réglisse', 'Anis', 'Épices douces', 'Finale longue'],
                'sig'       => 'Un miel sombre et fluide, réglissé, anisé et profondément aromatique.',
                'analysis'  => ['Humidité 14,1 %', 'pH 4,30', 'Conductivité 0,42 mS/cm', 'Pollen de nigelle 10 %', 'Miel de fleurs'],
                'url'       => '/miels/miel-nigelle-egypte',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel de Nigelle d’Égypte, texture veloutée brune',
            ],
            [
                'name'      => 'Miel d’Agrumes d’Égypte',
                'origin'    => 'ÉGYPTE',
                'texture'   => 'Fluide, légèrement sirupeuse',
                'intensity' => 3,
                'colorName' => 'Doré à ambré clair',
                'hex'       => '#E4B84A',
                'notes'     => ['Floral', 'Agrumes', 'Résine légère', 'Nuance épicée'],
                'sig'       => 'Le miel égyptien le plus lumineux, floral et facile à aimer.',
                'analysis'  => ['Humidité 14,6 %', 'Conductivité 0,24 mS/cm', 'Pollen d’agrumes 24 %'],
                'url'       => '/miels/miel-agrumes-egypte',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel d’Agrumes d’Égypte, texture fluide dorée',
            ],
            [
                'name'      => 'Miel blanc de Trèfle de Nouvelle-Zélande',
                'origin'    => 'NOUVELLE-ZÉLANDE',
                'texture'   => 'Crémeux',
                'intensity' => 2,
                'colorName' => 'Ivoire',
                'hex'       => '#F1E6C9',
                'notes'     => ['Lait', 'Vanille', 'Fleurs blanches'],
                'sig'       => 'Le miel blanc le plus doux, crémeux et familial de la sélection.',
                'analysis'  => [],
                'url'       => '/miels/miel-blanc-trefle-nouvelle-zelande',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel blanc de Trèfle de Nouvelle-Zélande, texture crémeuse ivoire',
            ],
            [
                'name'      => 'Miel blanc du Kirghizistan',
                'origin'    => 'KIRGHIZISTAN',
                'texture'   => 'Crémeuse, fondante, légèrement mousseuse',
                'intensity' => 3,
                'colorName' => 'Blanc nacré à ivoire',
                'hex'       => '#F5EFE3',
                'notes'     => ['Lacté', 'Floral', 'Malté', 'Finale légèrement poivrée'],
                'sig'       => 'Un miel blanc fin et fondant, doux mais plus singulier que le Trèfle.',
                'analysis'  => ['Humidité 17,2 %', 'Conductivité 0,14 mS/cm', 'Pollen de sainfoin 75 % des pollens de plantes mellifères'],
                'url'       => '/miels/miel-blanc-du-kirghizistan',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel blanc du Kirghizistan, texture fondante nacrée',
            ],
            [
                'name'      => 'Miel de Manuka MGO 831 / NPA 20+',
                'origin'    => 'NOUVELLE-ZÉLANDE',
                'texture'   => 'Dense, compacte, légèrement granuleuse',
                'intensity' => 5,
                'colorName' => 'Ambré foncé à brun',
                'hex'       => '#7E3F12',
                'notes'     => ['Boisé', 'Résineux', 'Épices douces', 'Légère amertume'],
                'sig'       => 'Un Manuka analysé, dense, boisé et long en finale.',
                'analysis'  => ['MGO 831 mg/kg', 'NPA 20+', 'DHA 1057 mg/kg', 'HMF 17,0 mg/kg'],
                'url'       => '/miels/miel-de-manuka-de-nouvelle-zelande-mgo845-npa20',
                'image'     => 'assets/images/placeholder.png',
                'alt'       => 'Miel de Manuka de Nouvelle-Zélande, texture dense brune',
            ],
        ];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function getFaqs(): array
    {
        return [
            [
                'question' => 'Comment déguster un miel correctement ?',
                'answer'   => 'Dégustez le miel à température ambiante, idéalement autour de 20 °C. Observez d’abord sa couleur et sa texture, puis goûtez une petite quantité à la cuillère. Commencez toujours par les miels les plus doux et terminez par les plus intenses afin de ne pas saturer le palais.',
            ],
            [
                'question' => 'Quel miel choisir pour commencer ?',
                'answer'   => 'Pour une première découverte, choisissez un miel doux et accessible comme le miel blanc de Trèfle de Nouvelle-Zélande, le miel blanc du Kirghizistan ou le miel d’Agrumes d’Égypte. Ils offrent des profils faciles à aimer, avant de passer à des miels plus marqués comme le Daghmous, la Nigelle, le Jujubier du Yémen ou le Manuka.',
            ],
            [
                'question' => 'Quelle différence entre le Jujubier du Yémen et le Jujubier du Pakistan ?',
                'answer'   => 'Le Jujubier du Yémen est plus dense, plus profond, plus caramélisé et plus long en bouche. Le Jujubier du Pakistan est plus fluide, plus doux et plus accessible. Les deux appartiennent à l’univers du jujubier, mais ils ne s’adressent pas exactement au même usage ni au même palais.',
            ],
            [
                'question' => 'Pourquoi certains miels sont-ils plus fluides que d’autres ?',
                'answer'   => 'La texture d’un miel dépend notamment de sa composition naturelle, de sa teneur en eau, du rapport entre ses sucres et de sa cristallisation. Certains miels restent fluides longtemps, tandis que d’autres deviennent crémeux, fondants ou plus compacts avec le temps.',
            ],
            [
                'question' => 'Que signifie un pourcentage de pollen dans une analyse de miel ?',
                'answer'   => 'Un pourcentage de pollen indique la part de certains pollens observés dans l’échantillon analysé. Il aide à comprendre l’origine botanique du miel, mais il ne doit pas être confondu avec un pourcentage de nectar. Chez Nidemiel, cette distinction est importante pour rester précis et transparent.',
            ],
            [
                'question' => 'La couleur d’un miel indique-t-elle son goût ?',
                'answer'   => 'La couleur donne une tendance, mais elle ne suffit pas à connaître le goût d’un miel. Les miels foncés sont souvent plus intenses, boisés ou résineux, mais certains miels clairs peuvent aussi avoir une finale puissante, comme l’Euphorbe du Yémen.',
            ],
            [
                'question' => 'Quel miel choisir si je veux un miel doux ?',
                'answer'   => 'Pour un miel doux, privilégiez le miel blanc de Trèfle de Nouvelle-Zélande, le miel blanc du Kirghizistan ou le miel d’Agrumes d’Égypte. Ces profils sont plus ronds, plus accessibles et faciles à intégrer dans les habitudes du quotidien.',
            ],
            [
                'question' => 'Quel miel choisir si je veux un miel de caractère ?',
                'answer'   => 'Pour un miel de caractère, orientez-vous vers le Daghmous du Maroc, l’Euphorbe du Yémen, la Nigelle d’Égypte, le Jujubier du Yémen ou le Manuka de Nouvelle-Zélande. Ces miels offrent des finales plus longues, des arômes plus marqués et une personnalité plus affirmée.',
            ],
        ];
    }
}
