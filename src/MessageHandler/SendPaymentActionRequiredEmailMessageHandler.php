<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendPaymentActionRequiredEmailMessage;
use App\Repository\OrderRepository;
use App\Enum\PaymentStatus;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendPaymentActionRequiredEmailMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailFromAddress,
        private string $mailFromName,
    ) {}

    public function __invoke(SendPaymentActionRequiredEmailMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            return;
        }

        // Si la commande est déjà payée entre-temps, inutile de relancer
        if ($order->getPaymentStatus() !== PaymentStatus::Pending) {
            return;
        }

        $user = $order->getUser();

        if (null === $user || !$user->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject('Finalisez votre paiement — ' . ($order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/payment_action_required.html.twig')
            ->context([
                'order'             => $order,
                'user'              => $user,
                'payment_intent_id' => $message->paymentIntentId,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email action requise paiement', [
                'order_id' => $message->orderId,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
