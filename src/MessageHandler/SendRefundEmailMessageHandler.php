<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendRefundEmailMessage;
use App\Repository\OrderRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendRefundEmailMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
        private string $mailFromAddress = 'contact@nidemiel.com',
        private string $mailFromName = 'Nidemiel',
    ) {}

    public function __invoke(SendRefundEmailMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            return;
        }

        $user = $order->getUser();

        if (null === $user || !$user->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject(\sprintf('Remboursement de votre commande %s', $order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/order_refund.html.twig')
            ->context([
                'order' => $order,
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }
}
