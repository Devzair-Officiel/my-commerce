<?php

namespace App\Service;

use App\Service\PaymentConfigResolver;

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
