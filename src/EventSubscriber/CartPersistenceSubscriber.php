<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Restaure le panier sauvegardé en BDD lors de la reconnexion de l'utilisateur.
 * La sauvegarde est faite en temps réel dans CartService à chaque modification du panier.
 */
final class CartPersistenceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $savedCart = $user->getSavedCart();

        if (empty($savedCart)) {
            return;
        }

        // Restaurer uniquement si le panier de session est vide
        if (empty($this->cartService->getRawCart())) {
            $this->cartService->setRawCart($savedCart);
        }

        // Effacer le panier sauvegardé après restauration
        $user->setSavedCart(null);
        $this->em->flush();
    }
}
