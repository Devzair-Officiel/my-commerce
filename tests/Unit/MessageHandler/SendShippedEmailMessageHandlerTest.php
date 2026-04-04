<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Order;
use App\Entity\User;
use App\Message\SendShippedEmailMessage;
use App\MessageHandler\SendShippedEmailMessageHandler;
use App\Repository\OrderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Ce que l'on teste :
 *  - Order introuvable → rien n'est envoyé
 *  - User sans email → rien n'est envoyé
 *  - Cas nominal → mailer appelé une fois
 */
class SendShippedEmailMessageHandlerTest extends TestCase
{
    private function makeUser(?string $email): User
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('getFirstname')->willReturn('Marie');

        return $user;
    }

    private function makeOrder(?User $user): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getUser')->willReturn($user);
        $order->method('getOrderReference')->willReturn('CMD-20260101-XYZ');
        $order->method('getShippingAddress')->willReturn('1 rue de la Paix, 75001 Paris');
        $order->method('getCarrierNameSnapshot')->willReturn('Colissimo');

        return $order;
    }

    public function testDoesNothingWhenOrderNotFound(): void
    {
        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $handler = new SendShippedEmailMessageHandler($repo, $mailer);
        ($handler)(new SendShippedEmailMessage(999));
    }

    public function testDoesNothingWhenUserHasNoEmail(): void
    {
        $order = $this->makeOrder($this->makeUser(null));

        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn($order);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $handler = new SendShippedEmailMessageHandler($repo, $mailer);
        ($handler)(new SendShippedEmailMessage(1));
    }

    public function testSendsEmailWhenOrderAndUserAreValid(): void
    {
        $order = $this->makeOrder($this->makeUser('client@example.com'));

        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn($order);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $handler = new SendShippedEmailMessageHandler($repo, $mailer);
        ($handler)(new SendShippedEmailMessage(1));
    }
}
