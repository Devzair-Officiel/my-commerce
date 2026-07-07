<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Order;
use App\Entity\User;
use App\Message\SendRefundEmailMessage;
use App\MessageHandler\SendRefundEmailMessageHandler;
use App\Repository\OrderRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Ce que l'on teste :
 *  - Order introuvable → rien n'est envoyé
 *  - User sans email → rien n'est envoyé
 *  - Cas nominal → mailer appelé une fois
 */
class SendRefundEmailMessageHandlerTest extends TestCase
{
    private function makeUser(?string $email): User
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('getFirstname')->willReturn('Jean');

        return $user;
    }

    private function makeOrder(?User $user, ?string $ref = 'CMD-20260101-ABC'): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getUser')->willReturn($user);
        $order->method('getOrderReference')->willReturn($ref);

        return $order;
    }

    private function makeHandler(OrderRepository $repo, MailerInterface $mailer): SendRefundEmailMessageHandler
    {
        return new SendRefundEmailMessageHandler(
            $repo,
            $mailer,
            new NullLogger(),
            'contact@example.com',
            'Nidemiel',
        );
    }

    public function testDoesNothingWhenOrderNotFound(): void
    {
        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $handler = $this->makeHandler($repo, $mailer);
        ($handler)(new SendRefundEmailMessage(999));
    }

    public function testDoesNothingWhenUserHasNoEmail(): void
    {
        $order = $this->makeOrder($this->makeUser(null));

        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn($order);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $handler = $this->makeHandler($repo, $mailer);
        ($handler)(new SendRefundEmailMessage(1));
    }

    public function testSendsEmailWhenOrderAndUserAreValid(): void
    {
        $order = $this->makeOrder($this->makeUser('client@example.com'));

        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn($order);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $handler = $this->makeHandler($repo, $mailer);
        ($handler)(new SendRefundEmailMessage(1));
    }

    public function testSendsEmailWithNullReference(): void
    {
        $order = $this->makeOrder($this->makeUser('client@example.com'), null);

        $repo = $this->createStub(OrderRepository::class);
        $repo->method('find')->willReturn($order);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $handler = $this->makeHandler($repo, $mailer);
        ($handler)(new SendRefundEmailMessage(1));
    }
}
