<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée ou met à jour une Invoice à partir d'une Order.
 * Appeler issue() pour émettre définitivement (verrouille la facture).
 */
final class InvoiceBuilder
{
    private const DEFAULT_NOTES = "TVA non applicable, art. 293 B du CGI\n"
        . "Pénalités de retard : taux légal en vigueur × 3\n"
        . "Pas d'escompte pour paiement anticipé.";

    public function __construct(
        private readonly InvoiceNumberGenerator  $numberGenerator,
        private readonly SettingRepository       $settingRepository,
        private readonly EntityManagerInterface  $em,
        private readonly InvoiceRepository       $invoiceRepository,
    ) {}

    /**
     * Crée la facture (status = draft) si elle n'existe pas encore pour cette commande.
     * Idempotent : retourne la facture existante sans la modifier si elle est déjà émise.
     */
    public function createForOrder(Order $order): Invoice
    {
        $existing = $this->invoiceRepository->findOneBy(['customerOrder' => $order]);

        if ($existing !== null) {
            return $existing;
        }

        $invoice = new Invoice();
        $invoice->setCustomerOrder($order);
        $invoice->setInvoiceNumber($this->numberGenerator->generate());
        $invoice->setSellerSnapshot($this->buildSellerSnapshot($order));
        $invoice->setNotes(self::DEFAULT_NOTES);

        $this->em->persist($invoice);

        return $invoice;
    }

    /**
     * Crée et émet immédiatement la facture (status = issued).
     * À appeler lors de la confirmation de paiement.
     */
    public function createAndIssueForOrder(Order $order): Invoice
    {
        $invoice = $this->createForOrder($order);

        if ($invoice->isDraft()) {
            $invoice->issue();
        }

        $this->em->flush();

        return $invoice;
    }

    private function buildSellerSnapshot(Order $order): array
    {
        $setting = $this->settingRepository->findOneBy([]);

        return [
            'name'                       => $setting?->getOwnerName() ?? $setting?->getWebsiteName() ?? '',
            'siret'                      => $setting?->getSiret() ?? '',
            'street'                     => $setting?->getStreet() ?? '',
            'city'                       => $setting?->getCity() ?? '',
            'code_postal'                => $setting?->getCodePostal() ?? '',
            'state'                      => $setting?->getState() ?? '',
            'phone'                      => $setting?->getPhone() ?? '',
            'email'                      => $setting?->getEmail() ?? '',
            'website_name'               => $setting?->getWebsiteName() ?? '',
            'logo_filename'              => $setting?->getLogoMedia()?->getFilename() ?? '',
            'legal_mentions'             => $setting?->getLegalMentions() ?? '',
            // Snapshot des infos de paiement au moment de l'émission
            'paid_at'                    => $order->getPaidAt()?->format('d/m/Y'),
            'payment_method_name'        => $order->getPaymentMethodNameSnapshot() ?? '',
        ];
    }
}
