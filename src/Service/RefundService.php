<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;

/**
 * Déclenche un remboursement complet via Stripe et met à jour le statut de la commande.
 * Ne fait pas de flush : l'appelant est responsable de persister.
 */
final class RefundService
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly StockAllocatorInterface $stockAllocator,
    ) {}

    /**
     * @throws \LogicException     si la commande n'est pas dans un état remboursable
     * @throws \RuntimeException   si aucune référence de paiement n'est présente
     * @throws ApiErrorException   si Stripe retourne une erreur
     */
    public function refundOrder(Order $order): void
    {
        if ($order->getPaymentStatus() !== PaymentStatus::Paye) {
            throw new \LogicException(\sprintf(
                'La commande #%d ne peut pas être remboursée (statut : %s).',
                (int) $order->getId(),
                $order->getPaymentStatus()->label(),
            ));
        }

        $paymentReference = $order->getPaymentReference();
        if (!$paymentReference) {
            throw new \RuntimeException(\sprintf(
                'La commande #%d n\'a pas de référence de paiement Stripe.',
                (int) $order->getId(),
            ));
        }

        Stripe::setApiKey($this->stripeService->getPrivateKey());

        // Remboursement complet du PaymentIntent
        Refund::create(['payment_intent' => $paymentReference]);

        // Le statut passe à Rembourse AVANT l'arrivée du webhook charge.refunded :
        // celui-ci verra la commande déjà remboursée et ne touchera pas au stock.
        // La restauration du stock doit donc être faite ici.
        $order->setPaymentStatus(PaymentStatus::Rembourse);
        $order->setFulfillmentStatus(FulfillmentStatus::Annule);
        $this->stockAllocator->restoreStockForCancelledOrder($order);
    }
}
