<?php

namespace App\Service;

use App\Service\PaymentConfigResolver;

/**
 * Fournit un accès simple et unique aux paramètres nécessaires pour initialiser Stripe côté serveur.
 * Délègue la sélection des valeurs (prod vs test) à un composant dédié, sans logique d’environnement dans le reste du code.
 * Règle : ne retourne que des valeurs validées (non vides), sinon une exception remonte depuis le résolveur.
 */
final class StripeService
{
    public function __construct(private readonly PaymentConfigResolver $config) {}

    public function getPublicKey(): string
    {
        return $this->config->stripePublicKey();
    }

    public function getPrivateKey(): string
    {
        return $this->config->stripeSecretKey();
    }
}
