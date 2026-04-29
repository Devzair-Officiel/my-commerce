<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendOrderConfirmationEmailMessage;
use App\Repository\OrderRepository;
use App\Service\Invoice\InvoiceBuilder;
use App\Service\Invoice\InvoicePdfRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Gestionnaire Messenger qui émet la facture et envoie l'e-mail de confirmation de commande avec le PDF en pièce jointe.
 */
#[AsMessageHandler]
final readonly class SendOrderConfirmationEmailMessageHandler
{
    public function __construct(
        private OrderRepository        $orderRepository,
        private EntityManagerInterface $em,
        private MailerInterface        $mailer,
        private InvoiceBuilder         $invoiceBuilder,
        private InvoicePdfRenderer     $pdfRenderer,
        private LoggerInterface        $logger,
        private string $mailFromAddress,
        private string $mailFromName,
    ) {}

    public function __invoke(SendOrderConfirmationEmailMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            return;
        }

        if ($order->isConfirmationEmailSent()) {
            return;
        }

        $user = $order->getUser();

        if (null === $user || !$user->getEmail()) {
            return;
        }

        $order->generateOrderReferenceIfMissing();

        // Créer et émettre la facture avant l'envoi de l'email
        $invoice = $this->invoiceBuilder->createAndIssueForOrder($order);

        $pdfContent = $this->pdfRenderer->render($invoice);
        $filename   = $this->pdfRenderer->getFilename($invoice);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject('Confirmation de votre commande ' . ($order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order'   => $order,
                'user'    => $user,
                'invoice' => $invoice,
            ])
            ->addPart(new DataPart($pdfContent, $filename, 'application/pdf'));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email confirmation commande', [
                'order_id' => $order->getId(),
                'error'    => $e->getMessage(),
            ]);
            throw $e; // Messenger retentera automatiquement
        }

        $order->markConfirmationEmailSent();
        $this->em->flush();
    }
}
