<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendDeliveredEmailMessage;
use App\Repository\ShipmentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendDeliveredEmailMessageHandler
{
    public function __construct(
        private ShipmentRepository $shipmentRepository,
        private MailerInterface    $mailer,
        private LoggerInterface    $logger,
        private string $mailFromAddress,
        private string $mailFromName,
    ) {}

    public function __invoke(SendDeliveredEmailMessage $message): void
    {
        $shipment = $this->shipmentRepository->find($message->shipmentId);

        if ($shipment === null) {
            return;
        }

        $order = $shipment->getCustomerOrder();
        $user  = $order?->getUser();

        if ($user === null || !$user->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject(\sprintf('Votre commande %s a été livrée', $order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/order_delivered.html.twig')
            ->context([
                'order'    => $order,
                'user'     => $user,
                'shipment' => $shipment,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email livraison', [
                'shipment_id' => $shipment->getId(),
                'reason'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
