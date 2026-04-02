<?php

namespace App\Service;

use App\Repository\PaymentMethodRepository;

/**
 * Centralise la résolution des paramètres de paiement selon l’environnement.
 * En production, lit les secrets depuis la configuration applicative ; hors production, récupère les clés de test en base via le dépôt.
 * Règle : si une valeur requise est absente ou vide, l’exécution échoue immédiatement avec une exception.
 */
final class PaymentConfigResolver
{
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly string $appEnv,
        private readonly ?string $stripePublicProd,
        private readonly ?string $stripeSecretProd,
    ) {}

    public function stripePublicKey(): string
    {
        if ($this->isProd()) {
            return $this->must($this->stripePublicProd, 'STRIPE_PUBLIC_KEY');
        }

        $pm = $this->paymentMethodRepository->findOneBy(['name' => 'stripe']);
        return $this->must($pm?->getTestPublicApiKey(), 'PaymentMethod(stripe).test_public_api_key');
    }

    public function stripeSecretKey(): string
    {
        if ($this->isProd()) {
            return $this->must($this->stripeSecretProd, 'STRIPE_SECRET_KEY');
        }

        $pm = $this->paymentMethodRepository->findOneBy(['name' => 'stripe']);
        return $this->must($pm?->getTestPrivateApiKey(), 'PaymentMethod(stripe).test_private_api_key');
    }

    private function isProd(): bool
    {
        return $this->appEnv === 'prod';
    }

    private function must(?string $value, string $name): string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            throw new \RuntimeException(\sprintf('Missing payment configuration: %s', $name));
        }
        return $value;
    }
}
