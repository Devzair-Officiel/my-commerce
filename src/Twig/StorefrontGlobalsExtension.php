<?php

namespace App\Twig;

use App\Storefront\StorefrontGlobalsProvider;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Extension Twig qui expose les données globales du storefront
 * à tous les templates Twig (base, header, footer, pages, etc.).
 *
 * Les variables sont injectées automatiquement avant le rendu,
 * sans passer par les controllers.
 */
final class StorefrontGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly StorefrontGlobalsProvider $provider
    ) {}

    /**
     * Retourne les variables Twig globales accessibles
     * dans n'importe quel template.
     */
    public function getGlobals(): array
    {
        return $this->provider->getGlobals();
    }
}
