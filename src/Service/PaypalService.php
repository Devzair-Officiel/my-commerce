<?php

namespace App\Service;

use App\Service\PaymentConfigResolver;

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
