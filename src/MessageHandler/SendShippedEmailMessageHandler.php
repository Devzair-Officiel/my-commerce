<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendShippedEmailMessage;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendShippedEmailMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
        private string $mailFromAddress,
        private string $mailFromName,
    ) {}

    public function __invoke(SendShippedEmailMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if (null === $order) {
            return;
        }

        $user = $order->getUser();

        if (null === $user || !$user->getEmail()) {
            return;
        }

        $shipment = $order->getShipments()->last() ?: null;

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject(\sprintf('Votre commande %s a été expédiée', $order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/order_shipped.html.twig')
            ->context([
                'order'    => $order,
                'user'     => $user,
                'shipment' => $shipment,
            ]);

        try {
            $this->mailer->send($email);
            $order->markShippingEmailSent();
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email expédition', [
                'order_id' => $order->getId(),
                'reason'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
