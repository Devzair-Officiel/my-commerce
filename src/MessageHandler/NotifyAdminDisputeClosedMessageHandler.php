<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NotifyAdminDisputeClosedMessage;
use App\Repository\OrderRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class NotifyAdminDisputeClosedMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailFromAddress,
        private string $mailFromName,
        private string $adminEmail,
    ) {}

    public function __invoke(NotifyAdminDisputeClosedMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            $this->logger->error('Clôture dispute reçue mais commande introuvable', [
                'order_id'   => $message->orderId,
                'dispute_id' => $message->disputeId,
            ]);
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to($this->adminEmail)
            ->subject(\sprintf('[Contestation %s] Commande %s',
                $message->status === 'won' ? 'gagnée ✅' : ($message->status === 'lost' ? 'perdue ❌' : 'clôturée'),
                $order->getOrderReference() ?? '#' . $order->getId()
            ))
            ->htmlTemplate('emails/admin_dispute_closed.html.twig')
            ->context([
                'order'      => $order,
                'dispute_id' => $message->disputeId,
                'status'     => $message->status,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email clôture dispute admin', [
                'order_id'   => $message->orderId,
                'dispute_id' => $message->disputeId,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
