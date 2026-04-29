<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendBackInStockAlertMessage;
use App\Repository\ProductRepository;
use App\Repository\StockAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendBackInStockAlertMessageHandler
{
    public function __construct(
        private ProductRepository    $productRepository,
        private StockAlertRepository $stockAlertRepository,
        private MailerInterface      $mailer,
        private EntityManagerInterface $em,
        private LoggerInterface      $logger,
        private string $mailFromAddress,
        private string $mailFromName,
    ) {}

    public function __invoke(SendBackInStockAlertMessage $message): void
    {
        $product = $this->productRepository->find($message->productId);

        if (null === $product) {
            return;
        }

        $alerts = $this->stockAlertRepository->findPendingByProduct($product);

        foreach ($alerts as $alert) {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailFromAddress, $this->mailFromName))
                ->to(new Address($alert->getEmail()))
                ->subject(\sprintf('"%s" est de nouveau disponible !', $product->getTitle()))
                ->htmlTemplate('emails/back_in_stock.html.twig')
                ->context([
                    'product'        => $product,
                    'recipientEmail' => $alert->getEmail(),
                ]);

            try {
                $this->mailer->send($email);
                $alert->markNotified();
            } catch (\Throwable $e) {
                $this->logger->error('Échec envoi alerte retour en stock', [
                    'product_id' => $product->getId(),
                    'email'      => $alert->getEmail(),
                    'reason'     => $e->getMessage(),
                ]);
            }
        }

        $this->em->flush();
    }
}
