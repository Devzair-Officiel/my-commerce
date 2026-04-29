<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NotifyAdminDisputeMessage;
use App\Repository\OrderRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class NotifyAdminDisputeMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailFromAddress,
        private string $mailFromName,
        private string $adminEmail,
    ) {}

    public function __invoke(NotifyAdminDisputeMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            $this->logger->error('Dispute reçu mais commande introuvable', [
                'order_id'   => $message->orderId,
                'dispute_id' => $message->disputeId,
            ]);
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to($this->adminEmail)
            ->subject(\sprintf('[URGENT] Contestation de paiement — %s', $order->getOrderReference() ?? '#' . $order->getId()))
            ->htmlTemplate('emails/admin_dispute_alert.html.twig')
            ->context([
                'order'        => $order,
                'dispute_id'   => $message->disputeId,
                'reason'       => $message->reason,
                'amount_cents' => $message->amountCents,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email alerte dispute admin', [
                'order_id'   => $message->orderId,
                'dispute_id' => $message->disputeId,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
