<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Enum\CarrierType;
use App\Repository\ShipmentRepository;
use App\Service\Carrier\ColissimoTrackingService;
use App\Service\Carrier\ShipmentService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur EasyAdmin pour la gestion des expéditions : consultation des étiquettes,
 * rafraîchissement manuel du suivi Colissimo et affichage des statuts colorés.
 */
final class ShipmentCrudController extends AbstractCrudController
{
    // Codes Colissimo → classe Bootstrap badge
    private const STATUS_BADGES = [
        'LIVCFM'  => 'success',
        'LIVGAR'  => 'success',
        'LIVDOM'  => 'success',
        'DISTRI'  => 'primary',
        'OUTDELIV'=> 'primary',
        'ENCOURS' => 'info',
        'INTRANS' => 'info',
        'TRANSIT' => 'info',
        'PCHCFM'  => 'secondary',
        'RETOUR'  => 'danger',
        'ANOMAL'  => 'danger',
        'RETN'    => 'danger',
    ];

    public function __construct(
        private readonly ShipmentService          $shipmentService,
        private readonly ShipmentRepository       $shipmentRepository,
        private readonly ColissimoTrackingService  $trackingService,
        private readonly EntityManagerInterface   $em,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Shipment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Expédition')
            ->setEntityLabelInPlural('Expéditions')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(30)
            ->setSearchFields(['trackingNumber', 'customerOrder.orderReference']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $createLabelAction = Action::new('createLabel', 'Créer étiquette', 'fa fa-tag')
            ->linkToCrudAction('createLabel')
            ->addCssClass('btn btn-sm btn-primary')
            ->displayIf(static fn (Shipment $s): bool =>
                $s->getLabelUrl() === null && $s->getCarrier()?->getType() !== CarrierType::Manual
            );

        $downloadLabelAction = Action::new('downloadLabel', 'Étiquette PDF', 'fa fa-download')
            ->linkToCrudAction('downloadLabel')
            ->addCssClass('btn btn-sm btn-success')
            ->displayIf(static fn (Shipment $s): bool => $s->getLabelUrl() !== null);

        $syncTrackingAction = Action::new('syncTracking', 'Sync suivi', 'fa fa-sync')
            ->linkToCrudAction('syncTracking')
            ->addCssClass('btn btn-sm btn-outline-secondary')
            ->displayIf(static fn (Shipment $s): bool =>
                $s->getTrackingNumber() !== null && $s->getDeliveredAt() === null
            );

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $createLabelAction)
            ->add(Crud::PAGE_DETAIL, $createLabelAction)
            ->add(Crud::PAGE_INDEX, $downloadLabelAction)
            ->add(Crud::PAGE_DETAIL, $downloadLabelAction)
            ->add(Crud::PAGE_INDEX, $syncTrackingAction)
            ->add(Crud::PAGE_DETAIL, $syncTrackingAction);
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield AssociationField::new('customerOrder', 'Commande');
            yield AssociationField::new('carrier', 'Transporteur');
            yield TextField::new('trackingNumber', 'N° Suivi');
            yield TextField::new('statusCode', 'Statut')
                ->formatValue(function (?string $value): string {
                    if (!$value) {
                        return '<span class="badge bg-light text-muted">–</span>';
                    }
                    $class = self::STATUS_BADGES[$value] ?? 'secondary';
                    return \sprintf('<span class="badge bg-%s">%s</span>', $class, htmlspecialchars($value));
                })
                ->renderAsHtml();
            yield DateTimeField::new('shippedAt', 'Expédié le');
            yield DateTimeField::new('deliveredAt', 'Livré le');
            yield DateTimeField::new('syncedAt', 'Dernière synchro');
            return;
        }

        yield FormField::addTab('Expédition')->setIcon('fa fa-truck');
        yield FormField::addPanel('Commande')->setIcon('fa fa-box');
        yield AssociationField::new('customerOrder', 'Commande')->setColumns(6);
        yield AssociationField::new('carrier', 'Transporteur')->setColumns(6);

        yield FormField::addPanel('Suivi')->setIcon('fa fa-map-marker-alt');
        yield TextField::new('trackingNumber', 'N° Suivi')->setColumns(6);
        yield UrlField::new('trackingUrl', 'URL Suivi')->setColumns(6);
        yield TextField::new('statusCode', 'Statut')
            ->setColumns(4)
            ->onlyOnDetail()
            ->formatValue(function (?string $value): string {
                if (!$value) {
                    return '<span class="badge bg-light text-muted">–</span>';
                }
                $class = self::STATUS_BADGES[$value] ?? 'secondary';
                return \sprintf('<span class="badge bg-%s fs-6 px-3 py-2">%s</span>', $class, htmlspecialchars($value));
            })
            ->renderAsHtml();
        yield IntegerField::new('weightGrams', 'Poids (g)')->setColumns(4);
        yield DateTimeField::new('shippedAt', 'Expédié le')->setColumns(4);
        yield DateTimeField::new('deliveredAt', 'Livré le')->setColumns(4)->onlyOnDetail();
        yield DateTimeField::new('syncedAt', 'Dernière synchro')->setColumns(4)->onlyOnDetail();

        yield FormField::addPanel('Point relais')->setIcon('fa fa-map-pin');
        yield TextField::new('pickupPointId', 'ID Point relais')->setColumns(3)->onlyOnDetail();
        yield TextField::new('pickupPointName', 'Nom')->setColumns(3)->onlyOnDetail();
        yield TextField::new('pickupPointAddress', 'Adresse')->setColumns(3)->onlyOnDetail();
        yield TextField::new('pickupPointCity', 'Ville')->setColumns(3)->onlyOnDetail();

        yield FormField::addPanel('Étiquette')->setIcon('fa fa-file-pdf');
        yield UrlField::new('labelUrl', 'URL Étiquette')->onlyOnDetail();
        yield TextField::new('errorMessage', 'Erreur éventuelle')->onlyOnDetail();
        yield DateTimeField::new('syncedAt', 'Dernière synchro')->onlyOnDetail();
    }

    /**
     * Action : appelle l'API transporteur et génère l'étiquette.
     * Le poids (weightGrams) doit avoir été saisi au préalable via l'édition.
     */
    public function createLabel(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        $entityId = $context->getRequest()->query->getInt('entityId');
        $shipment = $this->shipmentRepository->find($entityId);

        if (!$shipment instanceof Shipment) {
            $this->addFlash('danger', 'Expédition introuvable.');
            return $this->redirect($urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $order = $shipment->getCustomerOrder();
        if (!$order instanceof Order) {
            $this->addFlash('danger', 'Commande introuvable pour cette expédition.');
            return $this->redirect($urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl());
        }

        $weightGrams = $shipment->getWeightGrams() ?? 500;

        try {
            $this->shipmentService->createLabel($order, $weightGrams);
            $this->addFlash('success', \sprintf('Étiquette créée — N° suivi : %s', $shipment->getTrackingNumber()));
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', 'Erreur API transporteur : ' . $e->getMessage());
        }

        return $this->redirect(
            $urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl()
        );
    }

    /**
     * Action : télécharge l'étiquette PDF stockée.
     */
    public function downloadLabel(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        $entityId = $context->getRequest()->query->getInt('entityId');
        $shipment = $this->shipmentRepository->find($entityId);

        if (!$shipment instanceof Shipment || !$shipment->getLabelUrl()) {
            $this->addFlash('danger', 'Étiquette introuvable.');
            return $this->redirect($urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $labelUrl = $shipment->getLabelUrl();

        // Colissimo stocke le PDF en base64 data-URL, on le sert directement
        if (str_starts_with($labelUrl, 'data:application/pdf;base64,')) {
            $pdfContent = base64_decode(substr($labelUrl, strlen('data:application/pdf;base64,')));
            $filename = \sprintf('etiquette-%s.pdf', $shipment->getTrackingNumber() ?? $shipment->getId());

            return new Response($pdfContent, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
            ]);
        }

        // Mondial Relay retourne une URL publique — on redirige
        return $this->redirect($labelUrl);
    }

    /**
     * Action : synchronise manuellement le suivi Colissimo d'un colis.
     */
    public function syncTracking(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        $entityId = $context->getRequest()->query->getInt('entityId');
        $shipment = $this->shipmentRepository->find($entityId);

        if (!$shipment instanceof Shipment) {
            $this->addFlash('danger', 'Expédition introuvable.');
            return $this->redirect($urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        try {
            $inserted = $this->trackingService->syncShipment($shipment);
            $status   = $shipment->getStatusCode() ?? '?';

            $this->addFlash('success', \sprintf(
                'Suivi synchronisé — statut : %s — %d nouvel(s) événement(s) enregistré(s).',
                $status,
                $inserted,
            ));
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', 'Erreur lors de la synchronisation : ' . $e->getMessage());
        }

        return $this->redirect(
            $urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl()
        );
    }
}
