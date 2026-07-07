<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Enum\CarrierType;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Message\SendRefundEmailMessage;
use App\Repository\OrderRepository;
use App\Service\Carrier\ShipmentService;
use App\Service\RefundService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * Contrôleur EasyAdmin pour la gestion des commandes : consultation, changement de statut,
 * création d'étiquette d'expédition Colissimo et déclenchement de remboursements Stripe.
 */
final class OrderCrudController extends AbstractCrudController
{
    /** Id du token CSRF protégeant les actions sensibles (refund, expédition…). */
    private const ACTION_CSRF_ID = 'admin_order_action';

    public function __construct(
        private readonly RefundService $refundService,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly MessageBusInterface $bus,
        private readonly ShipmentService $shipmentService,
        private readonly LockFactory $lockFactory,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    /**
     * URL d'action custom signée d'un token CSRF : les actions EasyAdmin custom
     * sont des liens GET, sans token un admin connecté peut être piégé par un
     * simple lien externe (SameSite=Lax n'arrête pas les GET top-level).
     */
    private function actionUrl(string $action, Order $order): string
    {
        $token = $this->container->get('security.csrf.token_manager')
            ->getToken(self::ACTION_CSRF_ID)->getValue();

        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($order->getId())
            ->set('token', $token)
            ->generateUrl();
    }

    /** Rejette la requête si le token CSRF de l'action est absent ou invalide. */
    private function denyUnlessValidActionToken(AdminContext $context): void
    {
        $token = (string) $context->getRequest()->query->get('token', '');
        if (!$this->isCsrfTokenValid(self::ACTION_CSRF_ID, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(30)
            ->setSearchFields([
                'id',
                'orderReference',
                'user.email',
                'shippingAddress',
                'billingAddress',
                'paymentReference',
                'carrierNameSnapshot',
                'paymentMethodNameSnapshot',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $shipAction = Action::new('shipOrder', 'Expédier', 'fa fa-truck')
            ->linkToUrl(fn (Order $order): string => $this->actionUrl('shipOrder', $order))
            ->addCssClass('btn btn-sm btn-primary')
            ->displayIf(static fn (Order $order): bool =>
                $order->getPaymentStatus() === PaymentStatus::Paye
                && !\in_array($order->getFulfillmentStatus(), [FulfillmentStatus::Expedie, FulfillmentStatus::Livre, FulfillmentStatus::Annule], true)
                && $order->getCarrier() !== null
            );

        $refundAction = Action::new('refund', 'Rembourser', 'fa fa-sync-left')
            ->linkToUrl(fn (Order $order): string => $this->actionUrl('processRefund', $order))
            ->addCssClass('btn btn-sm btn-danger')
            ->setHtmlAttributes([
                'onclick' => 'return confirm("Rembourser intégralement cette commande via Stripe ?")',
            ])
            ->displayIf(static fn (Order $order): bool => $order->getPaymentStatus() === PaymentStatus::Paye);

        $sendRefundEmailAction = Action::new('sendRefundEmail', 'Envoyer email remboursement', 'fa fa-envelope')
            ->linkToUrl(fn (Order $order): string => $this->actionUrl('sendRefundEmail', $order))
            ->addCssClass('btn btn-sm btn-outline-danger')
            ->setHtmlAttributes([
                'onclick' => 'return confirm("Envoyer un email de confirmation de remboursement au client ?")',
            ])
            ->displayIf(static fn (Order $order): bool => $order->getPaymentStatus() === PaymentStatus::Rembourse && !$order->isRefundEmailSent());

        $refundEmailSentAction = Action::new('refundEmailSent', 'Email remboursement envoyé ✓', 'fa fa-check')
            ->linkToCrudAction('sendRefundEmail')
            ->addCssClass('btn btn-sm btn-outline-secondary')
            ->setHtmlAttributes([
                'onclick' => 'return false;',
                'style' => 'opacity:.5; pointer-events:none; cursor:default;',
                'aria-disabled' => 'true',
            ])
            ->displayIf(static fn (Order $order): bool => $order->isRefundEmailSent());

        $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $shipAction)
            ->add(Crud::PAGE_DETAIL, $shipAction);

        // Remboursement réservé aux admins
        if ($this->isGranted('ROLE_ADMIN')) {
            $actions
                ->add(Crud::PAGE_INDEX, $refundAction)
                ->add(Crud::PAGE_DETAIL, $refundAction)
                ->add(Crud::PAGE_INDEX, $sendRefundEmailAction)
                ->add(Crud::PAGE_DETAIL, $sendRefundEmailAction)
                ->add(Crud::PAGE_INDEX, $refundEmailSentAction)
                ->add(Crud::PAGE_DETAIL, $refundEmailSentAction);
        }

        return $actions;
    }

    public function processRefund(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Remboursement réservé aux administrateurs.');
        }
        $this->denyUnlessValidActionToken($context);

        $entityId = $context->getRequest()->query->getInt('entityId');
        $order = $this->orderRepository->find($entityId);

        if (!$order instanceof Order) {
            $this->addFlash('danger', 'Commande introuvable.');
            return $this->redirect(
                $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
            );
        }

        $lock = $this->lockFactory->createLock('order_refund_' . $order->getId(), ttl: 30);

        if (!$lock->acquire()) {
            $this->addFlash('warning', 'Un remboursement est déjà en cours pour cette commande.');
            return $this->redirect(
                $urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($order->getId())->generateUrl()
            );
        }

        try {
            // Transaction requise : la restauration du stock verrouille les produits
            // (PESSIMISTIC_WRITE) et doit être atomique avec le changement de statut.
            $this->em->wrapInTransaction(function () use ($order): void {
                $this->refundService->refundOrder($order);
            });
            $this->addFlash('success', \sprintf(
                'Commande #%d remboursée avec succès.',
                (int) $order->getId(),
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', \sprintf(
                'Erreur lors du remboursement : %s',
                $e->getMessage(),
            ));
        } finally {
            $lock->release();
        }

        return $this->redirect(
            $urlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($order->getId())
                ->generateUrl()
        );
    }

    public function sendRefundEmail(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        $this->denyUnlessValidActionToken($context);

        $entityId = $context->getRequest()->query->getInt('entityId');
        $order = $this->orderRepository->find($entityId);

        if (!$order instanceof Order) {
            $this->addFlash('danger', 'Commande introuvable.');
            return $this->redirect(
                $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
            );
        }

        if ($order->isRefundEmailSent()) {
            $this->addFlash('warning', 'L\'email de remboursement a déjà été envoyé pour cette commande.');
            return $this->redirect(
                $urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($order->getId())->generateUrl()
            );
        }

        $lock = $this->lockFactory->createLock('order_refund_email_' . $order->getId(), ttl: 30);

        if (!$lock->acquire()) {
            $this->addFlash('warning', 'L\'envoi de l\'email est déjà en cours pour cette commande.');
            return $this->redirect(
                $urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($order->getId())->generateUrl()
            );
        }

        try {
            // Marquer comme envoyé immédiatement pour que le bouton se désactive dès le rechargement
            $order->markRefundEmailSent();
            $this->em->flush();
            $this->bus->dispatch(new SendRefundEmailMessage((int) $order->getId()));
            $this->addFlash('success', \sprintf(
                'Email de remboursement envoyé pour la commande #%d.',
                (int) $order->getId(),
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', \sprintf(
                'Erreur lors de l\'envoi de l\'email : %s',
                $e->getMessage(),
            ));
        } finally {
            $lock->release();
        }

        return $this->redirect(
            $urlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($order->getId())
                ->generateUrl()
        );
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user'))
            ->add(ChoiceFilter::new('paymentStatus')->setChoices($this->paymentChoices())->setFormTypeOption('value_type_options.choice_value', static fn ($v) => $v instanceof \BackedEnum ? $v->value : $v))
            ->add(ChoiceFilter::new('fulfillmentStatus')->setChoices($this->fulfillmentChoices())->canSelectMultiple()->setFormTypeOption('value_type_options.choice_value', static fn ($v) => $v instanceof \BackedEnum ? $v->value : $v))
            ->add(DateTimeFilter::new('paidAt'));
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IntegerField::new('id', 'Id');

        $paymentStatus = ChoiceField::new('paymentStatus', 'Paiement')
            ->setChoices($this->paymentChoices())
            ->renderAsBadges($this->paymentBadges());

        $fulfillmentStatus = ChoiceField::new('fulfillmentStatus', 'Livraison')
            ->setChoices($this->fulfillmentChoices())
            ->renderAsBadges($this->fulfillmentBadges());

        $userEmail = TextField::new('user.email', 'Client');

        $shippingAddress = TextareaField::new('shippingAddress', 'Adresse de livraison (snapshot)')
            ->setFormTypeOption('attr', ['rows' => 5]);

        $billingAddress = TextareaField::new('billingAddress', 'Adresse de facturation (snapshot)')
            ->setFormTypeOption('attr', ['rows' => 5]);

        $weight = TextField::new('totalWeightKgFormatted', 'Poids total');

        $orderTotal = MoneyField::new('orderTotalTtcCents', 'Total TTC')
            ->setStoredAsCents(true)
            ->setCurrencyPropertyPath('currency');

        $carrierName = TextField::new('carrierNameSnapshot', 'Transporteur (snapshot)');
        $paymentMethodName = TextField::new('paymentMethodNameSnapshot', 'Moyen de paiement (snapshot)');
        $paymentReference = TextField::new('paymentReference', 'Référence paiement')->setRequired(false);
        $orderReference = TextField::new('orderReference', 'Référence commande')->setRequired(false);

        $paidAt = DateTimeField::new('paidAt', 'Payée le')->setRequired(false);
        $cartClearedAt = DateTimeField::new('cartClearedAt', 'Panier vidé le')->setRequired(false);

        if (Crud::PAGE_INDEX === $pageName) {
            return [
                $id,
                $paymentStatus,
                $fulfillmentStatus,
                $userEmail,
                TextField::new('shippingAddress', 'Livraison')->setMaxLength(80),
                $weight,
                $orderTotal,
                $paidAt,
            ];
        }

        if (Crud::PAGE_EDIT === $pageName) {
            // ✅ SEULEMENT ces 2 champs modifiables
            return [
                FormField::addPanel('Statuts'),
                $paymentStatus,
                $fulfillmentStatus,
            ];
        }

        // DETAIL : tout afficher, organisé
        return [
            FormField::addTab('Articles'),
            Field::new('orderItemsPanel', 'Articles commandés')
                ->setVirtual(true)
                ->setTemplatePath('admin/fields/order_details.html.twig')
                ->setLabel(false),

            FormField::addTab('Statuts'),
            $paymentStatus,
            $fulfillmentStatus,
            $userEmail,

            FormField::addTab('Adresses'),
            $shippingAddress,
            $billingAddress,

            FormField::addTab('Transport & paiement'),
            $carrierName,
            $paymentMethodName,
            $paymentReference,
            $orderReference,
            $paidAt,
            $weight,

            FormField::addTab('Tech'),
            $cartClearedAt,
        ];
    }

    private function paymentChoices(): array
    {
        return [
            PaymentStatus::Attente->label() => PaymentStatus::Attente,
            PaymentStatus::Paye->label() => PaymentStatus::Paye,
            PaymentStatus::Rembourse->label() => PaymentStatus::Rembourse,
            PaymentStatus::Echoue->label() => PaymentStatus::Echoue,
            PaymentStatus::Conteste->label() => PaymentStatus::Conteste,
        ];
    }

    private function fulfillmentChoices(): array
    {
        return [
            FulfillmentStatus::Brouillon->label() => FulfillmentStatus::Brouillon,
            FulfillmentStatus::Preparation->label() => FulfillmentStatus::Preparation,
            FulfillmentStatus::Expedie->label() => FulfillmentStatus::Expedie,
            FulfillmentStatus::Livre->label() => FulfillmentStatus::Livre,
            FulfillmentStatus::Annule->label() => FulfillmentStatus::Annule,
        ];
    }

    private function paymentBadges(): array
    {
        return [
            PaymentStatus::Attente->value => 'warning',
            PaymentStatus::Paye->value => 'success',
            PaymentStatus::Rembourse->value => 'danger',
            PaymentStatus::Echoue->value => 'danger',
            PaymentStatus::Conteste->value => 'warning',
        ];
    }

    private function fulfillmentBadges(): array
    {
        return [
            FulfillmentStatus::Brouillon->value => 'secondary',
            FulfillmentStatus::Preparation->value => 'info',
            FulfillmentStatus::Expedie->value => 'primary',
            FulfillmentStatus::Livre->value => 'success',
            FulfillmentStatus::Annule->value => 'danger',
        ];
    }

    /**
     * Action : crée une expédition + étiquette pour la commande.
     * Le poids par défaut est 500 g ; modifiable via l'édition du Shipment ensuite.
     */
    public function shipOrder(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        $this->denyUnlessValidActionToken($context);

        $entityId = $context->getRequest()->query->getInt('entityId');
        $order = $this->orderRepository->find($entityId);

        if (!$order instanceof Order) {
            $this->addFlash('danger', 'Commande introuvable.');
            return $this->redirect($urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        // Poids par défaut 500 g — l'admin peut ajuster via l'édition du Shipment
        $weightGrams = $context->getRequest()->query->getInt('weight', 500);

        try {
            $carrier = $order->getCarrier();
            $existingShipment = $order->getShipments()->first() ?: null;

            if ($carrier?->getType() === CarrierType::Manual) {
                if ($existingShipment instanceof Shipment) {
                    $existingShipment->setShippedAt(new \DateTimeImmutable());
                } else {
                    $shipment = new Shipment();
                    $shipment->setCustomerOrder($order);
                    $shipment->setCarrier($carrier);
                    $shipment->setWeightGrams($weightGrams);
                    $shipment->setTrackingNumber('À renseigner');
                    $shipment->setTrackingUrl('');
                    $shipment->setCreatedAt(new \DateTimeImmutable());
                    $shipment->setShippedAt(new \DateTimeImmutable());
                    $this->em->persist($shipment);
                }
            } else {
                if (!$existingShipment instanceof Shipment) {
                    $this->shipmentService->createLabel($order, $weightGrams);
                }
            }

            $order->setFulfillmentStatus(FulfillmentStatus::Expedie);
            $this->em->flush();

            $this->addFlash('success', \sprintf(
                'Commande %s marquée comme expédiée.',
                $order->getOrderReference() ?? '#' . $order->getId()
            ));
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', 'Erreur lors de l\'expédition : ' . $e->getMessage());
        }

        return $this->redirect(
            $urlGenerator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl()
        );
    }
}
