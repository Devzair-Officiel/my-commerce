<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendBackInStockAlertMessage;
use App\Repository\ProductRepository;
use App\Repository\StockAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private string $mailFromAddress = 'contact@nidemiel.com',
        private string $mailFromName    = 'Nidemiel',
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

            $this->mailer->send($email);
            $alert->markNotified();
        }

        $this->em->flush();
    }
}
