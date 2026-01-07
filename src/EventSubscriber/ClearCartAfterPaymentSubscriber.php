<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\OrderStatus;
use App\Service\CartService;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ClearCartAfterPaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRepository $orderRepository,
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $order = $this->orderRepository->findOneBy(
            [
                'user' => $user,
                'status' => OrderStatus::Paid,
                'cartClearedAt' => null,
            ],
            ['paidAt' => 'DESC']
        );

        if ($order === null) {
            return;
        }

        $this->cartService->clearCart();

        $order->markCartCleared();
        $this->em->flush();
    }
}
