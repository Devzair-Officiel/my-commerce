<?php

namespace App\Twig;

use App\Storefront\StorefrontGlobalsProvider;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class StorefrontGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly StorefrontGlobalsProvider $provider
    ) {}

    public function getGlobals(): array
    {
        return $this->provider->getGlobals();
    }
}
