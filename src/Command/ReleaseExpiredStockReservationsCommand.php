<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Order;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use App\Service\StockAllocatorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Libère les réservations de stock des checkouts abandonnés.
 *
 * Le checkout décrémente le stock dès l'affichage de la page de paiement
 * (réservation). Si le client ne paie jamais, le stock resterait bloqué
 * indéfiniment : cette commande restitue les quantités des commandes
 * brouillon dont la réservation dépasse l'âge maximum.
 *
 * À planifier en cron (ex. toutes les 10 minutes) :
 *   bin/console app:stock:release-expired-reservations
 *
 * Un paiement qui aboutirait après la libération reste géré par le webhook :
 * celui-ci re-décrémente le stock, ou rembourse automatiquement si le stock
 * a été racheté entre-temps.
 */
#[AsCommand(
    name: 'app:stock:release-expired-reservations',
    description: 'Libère les réservations de stock des checkouts abandonnés (brouillons expirés).',
)]
final class ReleaseExpiredStockReservationsCommand extends Command
{
    private const DEFAULT_MAX_AGE_MINUTES = 30;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $em,
        private readonly StockAllocatorInterface $stockAllocator,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-age-minutes', null, InputOption::VALUE_REQUIRED, 'Âge maximum d\'une réservation avant libération', (string) self::DEFAULT_MAX_AGE_MINUTES)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les commandes concernées sans rien modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $maxAgeMinutes = max(1, (int) $input->getOption('max-age-minutes'));
        $dryRun = (bool) $input->getOption('dry-run');

        // Une seule instance à la fois (cron qui se chevauche, exécution manuelle…)
        $lock = $this->lockFactory->createLock('release_expired_stock_reservations', ttl: 300);
        if (!$lock->acquire()) {
            $io->warning('Une autre exécution est déjà en cours.');
            return Command::SUCCESS;
        }

        try {
            $before = new \DateTimeImmutable(sprintf('-%d minutes', $maxAgeMinutes));
            $ids = $this->orderRepository->findExpiredStockReservationIds($before);

            if ($ids === []) {
                $io->success('Aucune réservation expirée.');
                return Command::SUCCESS;
            }

            if ($dryRun) {
                $io->table(['Commandes à libérer'], array_map(static fn (int $id): array => [$id], $ids));
                return Command::SUCCESS;
            }

            $released = 0;
            foreach ($ids as $orderId) {
                try {
                    $this->em->wrapInTransaction(function () use ($orderId, $before, &$released): void {
                        /** @var Order|null $order */
                        $order = $this->em->find(Order::class, $orderId, LockMode::PESSIMISTIC_WRITE);

                        // Revalidation sous verrou : le webhook a pu confirmer le paiement
                        // (ou le client relancer un checkout) entre la sélection et ici.
                        if ($order === null
                            || !$order->isStockReserved()
                            || $order->getStockReservedAt() >= $before
                            || $order->getPaymentStatus() !== PaymentStatus::Attente
                            || $order->getFulfillmentStatus() !== FulfillmentStatus::Brouillon
                        ) {
                            return;
                        }

                        $this->stockAllocator->releaseStockReservation($order);
                        $this->em->flush();

                        ++$released;

                        $this->logger->info('Réservation de stock expirée libérée', [
                            'order_id' => $orderId,
                        ]);
                    });
                } catch (\Throwable $e) {
                    $this->logger->error('Échec de libération d\'une réservation expirée', [
                        'order_id' => $orderId,
                        'exception' => $e,
                    ]);
                    $io->warning(sprintf('Commande #%d : %s', $orderId, $e->getMessage()));
                }

                // Détache les entités pour garder une consommation mémoire stable
                $this->em->clear();
            }

            $io->success(sprintf('%d réservation(s) libérée(s) sur %d candidate(s).', $released, \count($ids)));

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
