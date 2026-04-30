<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendReviewRequestEmailMessage;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendReviewRequestEmailMessageHandler
{
    public function __construct(
        private OrderRepository        $orderRepository,
        private ProductRepository      $productRepository,
        private EntityManagerInterface $em,
        private MailerInterface        $mailer,
        private LoggerInterface        $logger,
        private string                 $mailFromAddress,
        private string                 $mailFromName,
    ) {}

    public function __invoke(SendReviewRequestEmailMessage $message): void
    {
        $order = $this->orderRepository->find($message->orderId);

        if ($order === null) {
            return;
        }

        // Guard : ne jamais envoyer deux fois
        if ($order->hasReviewRequestEmailBeenSent()) {
            return;
        }

        $user = $order->getUser();

        if ($user === null || !$user->getEmail()) {
            return;
        }

        // Récupère les produits achetés avec leur slug pour les liens d'avis
        $products = [];
        foreach ($order->getOrderDetails() as $detail) {
            if ($detail->getProductId() === null) {
                continue;
            }
            $product = $this->productRepository->find($detail->getProductId());
            if ($product !== null) {
                $products[] = [
                    'name'     => $detail->getProductName(),
                    'slug'     => $product->getSlug(),
                    'imageUrl' => $detail->getProductImageUrl(),
                ];
            }
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($user->getEmail()))
            ->subject(\sprintf('Votre avis compte — commande %s', $order->getOrderReference() ?? ''))
            ->htmlTemplate('emails/review_request.html.twig')
            ->context([
                'order'    => $order,
                'user'     => $user,
                'products' => $products,
            ]);

        try {
            $this->mailer->send($email);
            $order->markReviewRequestEmailSent();
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email demande d\'avis', [
                'order_id' => $order->getId(),
                'reason'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
