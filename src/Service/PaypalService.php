<?php

namespace App\Services;

use App\Repository\PaymentMethodRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class PaypalService
{
    private $session;
    public function __construct(private RequestStack $requestStack, private PaymentMethodRepository $paymentMethodRepo)
    {
        $this->session = $requestStack->getSession();
    }

    public function getPublicKey()
    {
        $config = $this->paymentMethodRepo->findOneByName("paypal");
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
        $config = $this->paymentMethodRepo->findOneByName("paypal");
        if ($_ENV['APP_ENV'] === 'dev') {
            // development
            return $config->getTestPrivateApiKey();
        } else {
            // prod
            return $config->getProdPrivateApiKey();
        }
    }

    public function getBaseUrl()
    {
        $config = $this->paymentMethodRepo->findOneByName("Paypal");

        if ($_ENV['APP_ENV'] === 'dev') {
            //development
            return $config->getTestBaseUrl();
        } else {
            //production
            return $config->getProdBaseUrl();
        }
    }
}
