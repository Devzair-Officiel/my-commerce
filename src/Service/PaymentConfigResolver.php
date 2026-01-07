<?php

namespace App\Service;

use App\Repository\PaymentMethodRepository;

final class PaymentConfigResolver
{
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly string $appEnv,
        private readonly ?string $stripePublicProd,
        private readonly ?string $stripeSecretProd,
        private readonly ?string $paypalClientIdProd,
        private readonly ?string $paypalSecretProd,
        private readonly ?string $paypalBaseUrlProd,
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

    public function paypalClientId(): string
    {
        if ($this->isProd()) {
            return $this->must($this->paypalClientIdProd, 'PAYPAL_CLIENT_ID');
        }

        $pm = $this->paymentMethodRepository->findOneBy(['name' => 'paypal']);
        return $this->must($pm?->getTestPublicApiKey(), 'PaymentMethod(paypal).test_public_api_key');
    }

    public function paypalSecret(): string
    {
        if ($this->isProd()) {
            return $this->must($this->paypalSecretProd, 'PAYPAL_SECRET');
        }

        $pm = $this->paymentMethodRepository->findOneBy(['name' => 'paypal']);
        return $this->must($pm?->getTestPrivateApiKey(), 'PaymentMethod(paypal).test_private_api_key');
    }

    public function paypalBaseUrl(): string
    {
        if ($this->isProd()) {
            // Par défaut PayPal prod base URL est stable, tu peux aussi la hardcoder
            return $this->paypalBaseUrlProd ?? 'https://api-m.paypal.com';
        }

        $pm = $this->paymentMethodRepository->findOneBy(['name' => 'paypal']);
        return $this->must($pm?->getTestBaseUrl(), 'PaymentMethod(paypal).testBaseUrl');
    }

    private function isProd(): bool
    {
        return $this->appEnv === 'prod';
    }

    private function must(?string $value, string $name): string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            throw new \RuntimeException(sprintf('Missing payment configuration: %s', $name));
        }
        return $value;
    }
}
