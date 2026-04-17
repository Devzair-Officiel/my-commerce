<?php

namespace App\Command;

use App\Repository\ShipmentRepository;
use App\Service\Carrier\ColissimoTrackingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande planifiable qui interroge l'API Colissimo pour mettre à jour
 * les statuts de suivi des expéditions en cours d'acheminement.
 */
#[AsCommand(
    name: 'app:sync-shipment-tracking',
    description: 'Synchronise les statuts de suivi des colis Colissimo en cours d\'acheminement.',
)]
final class SyncShipmentTrackingCommand extends Command
{
    public function __construct(
        private readonly ShipmentRepository       $shipmentRepository,
        private readonly ColissimoTrackingService  $trackingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'tracking-number',
            't',
            InputOption::VALUE_OPTIONAL,
            'Synchroniser uniquement ce numéro de suivi'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $specificTracking = $input->getOption('tracking-number');

        if ($specificTracking) {
            $shipments = $this->shipmentRepository->findBy(['trackingNumber' => $specificTracking]);
            if (!$shipments) {
                $io->error("Aucun colis trouvé pour le numéro : $specificTracking");
                return Command::FAILURE;
            }
        } else {
            $shipments = $this->shipmentRepository->findActiveShipments();
        }

        if (!$shipments) {
            $io->info('Aucun colis actif à synchroniser.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Synchronisation de %d colis', \count($shipments)));

        $totalInserted = 0;
        $errors        = 0;

        foreach ($shipments as $shipment) {
            $tracking = $shipment->getTrackingNumber();
            $io->write(sprintf('  → %s ... ', $tracking));

            try {
                $inserted = $this->trackingService->syncShipment($shipment);
                $status   = $shipment->getStatusCode() ?? '?';

                $io->writeln(sprintf('<info>%d nouvel(s) événement(s)</info> [%s]', $inserted, $status));
                $totalInserted += $inserted;
            } catch (\Throwable $e) {
                $io->writeln('<error>ERREUR : ' . $e->getMessage() . '</error>');
                $errors++;
            }
        }

        $io->newLine();
        $io->success(sprintf(
            '%d colis traités — %d nouveaux événements — %d erreur(s)',
            \count($shipments),
            $totalInserted,
            $errors
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
