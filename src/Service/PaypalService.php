<?php

namespace App\Service;

use App\Service\PaymentConfigResolver;

/**
 * Fournit un accès simple et unique aux paramètres nécessaires pour appeler l’API PayPal.
 * Délègue la sélection des valeurs (prod vs test) à un composant dédié, sans stocker de secrets localement.
 * Règle : ne retourne que des valeurs validées (non vides), sinon une exception remonte depuis le résolveur.
 */
final class PaypalService
{
    public function __construct(private readonly PaymentConfigResolver $config) {}

    public function getPublicKey(): string
    {
        return $this->config->paypalClientId();
    }

    public function getPrivateKey(): string
    {
        return $this->config->paypalSecret();
    }

    public function getBaseUrl(): string
    {
        return $this->config->paypalBaseUrl();
    }
}
