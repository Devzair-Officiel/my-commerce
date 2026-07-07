<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Controller\Admin\ReviewCrudController;
use App\Message\SendNewReviewNotificationMessage;
use App\Repository\ReviewRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final readonly class SendNewReviewNotificationMessageHandler
{
    public function __construct(
        private ReviewRepository       $reviewRepository,
        private MailerInterface        $mailer,
        private AdminUrlGenerator      $adminUrlGenerator,
        private LoggerInterface        $logger,
        private string $mailFromAddress,
        private string $mailFromName,
        private string $adminEmail,
    ) {}

    public function __invoke(SendNewReviewNotificationMessage $message): void
    {
        $review = $this->reviewRepository->find($message->reviewId);

        if (null === $review) {
            return;
        }

        $adminUrl = $this->adminUrlGenerator
            ->setController(ReviewCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to(new Address($this->adminEmail))
            ->subject('Nouvel avis en attente de modération — ' . $review->getProduct()?->getTitle())
            ->htmlTemplate('emails/new_review_notification.html.twig')
            ->context([
                'review'     => $review,
                'product'    => $review->getProduct(),
                'user'       => $review->getUser(),
                'adminUrl'   => $adminUrl,
                'brandName'  => $this->mailFromName,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi notification nouvel avis', [
                'review_id' => $review->getId(),
                'reason'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
