<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\HoneyTastingSchema;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose la fonction Twig `honey_tasting_group(data)` qui regroupe les
 * évaluations brutes d'une fiche selon la hiérarchie miel → section → champ.
 */
final class HoneyTastingExtension extends AbstractExtension
{
    public function __construct(private readonly HoneyTastingSchema $schema) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('honey_tasting_group', $this->schema->groupForDisplay(...)),
        ];
    }
}
