<?php

namespace App\Services;

use App\Repository\PaymentMethodRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class StripeService
{
    private $session;
    public function __construct(private RequestStack $requestStack, private PaymentMethodRepository $paymentMethodRepo)
    {
        $this->session = $requestStack->getSession();
    }

    public function getPublicKey()
    {
        $config = $this->paymentMethodRepo->findOneByName("stripe");
        if ($_ENV['APP_ENV'] === 'dev') {
            // development
            return $config->getTestPublicApiKey();
        } else {
            // prod
            return $config->getProdPublicApiKey();
        }
    }

    public function getPrivateKey()
    {
        $config = $this->paymentMethodRepo->findOneByName("stripe");
        if ($_ENV['APP_ENV'] === 'dev') {
            // development
            return $config->getTestPrivateApiKey();
        } else {
            // prod
            return $config->getProdPrivateApiKey();
        }
    }
}
