<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendOrderConfirmationEmailMessage;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendOrderConfirmationEmailMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private string $mailFromAddress = 'contact@nidemiel.com',
        private string $mailFromName = 'Nidemiel',
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
        $this->em->flush();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject(sprintf('Confirmation de votre commande %s', $order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
                'user' => $user,
            ]);

        $this->mailer->send($email);

        $order->markConfirmationEmailSent();
        $this->em->flush();
    }
}
