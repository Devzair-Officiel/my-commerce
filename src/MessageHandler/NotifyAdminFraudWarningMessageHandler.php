<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NotifyAdminFraudWarningMessage;
use App\Repository\OrderRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class NotifyAdminFraudWarningMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailFromAddress,
        private string $mailFromName,
        private string $adminEmail,
    ) {}

    public function __invoke(NotifyAdminFraudWarningMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            $this->logger->error('Alerte fraude reçue mais commande introuvable', [
                'order_id'         => $message->orderId,
                'fraud_warning_id' => $message->fraudWarningId,
            ]);
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to($this->adminEmail)
            ->subject(\sprintf('[ALERTE FRAUDE] Commande suspecte — %s', $order->getOrderReference() ?? '#' . $order->getId()))
            ->htmlTemplate('emails/admin_fraud_warning.html.twig')
            ->context([
                'order'            => $order,
                'fraud_warning_id' => $message->fraudWarningId,
                'fraud_type'       => $message->fraudType,
                'actionable'       => $message->actionable,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email alerte fraude admin', [
                'order_id'         => $message->orderId,
                'fraud_warning_id' => $message->fraudWarningId,
                'error'            => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
