<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

final class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RefundService $refundService,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
    ) {}

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
        $refundAction = Action::new('refund', 'Rembourser', 'fa fa-rotate-left')
            ->linkToCrudAction('processRefund')
            ->addCssClass('btn btn-sm btn-danger')
            ->displayIf(static fn (Order $order): bool => $order->getPaymentStatus() === PaymentStatus::Paid);

        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $refundAction)
            ->add(Crud::PAGE_DETAIL, $refundAction);
    }

    public function processRefund(AdminContext $context, AdminUrlGenerator $urlGenerator): Response
    {
        $entityId = $context->getRequest()->query->getInt('entityId');
        $order = $this->orderRepository->find($entityId);

        if (!$order instanceof Order) {
            $this->addFlash('danger', 'Commande introuvable.');
            return $this->redirect(
                $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
            );
        }

        try {
            $this->refundService->refundOrder($order);
            $this->em->flush();
            $this->addFlash('success', \sprintf(
                'Commande #%d remboursée avec succès.',
                (int) $order->getId(),
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', \sprintf(
                'Erreur lors du remboursement : %s',
                $e->getMessage(),
            ));
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
            ->add(ChoiceFilter::new('paymentStatus')->setChoices($this->paymentChoices()))
            ->add(ChoiceFilter::new('fulfillmentStatus')->setChoices($this->fulfillmentChoices()))
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

        $itemsTotal = MoneyField::new('itemsTotalHtCents', 'Articles HT')
            ->setStoredAsCents(true)
            ->setCurrencyPropertyPath('currency');

        $taxAmount = MoneyField::new('taxAmountCents', 'TVA')
            ->setStoredAsCents(true)
            ->setCurrencyPropertyPath('currency');

        $weight = TextField::new('totalWeightKgFormatted', 'Poids total');

        $carrierPrice = MoneyField::new('carrierPriceSnapshotCents', 'Frais de port')
            ->setStoredAsCents(true)
            ->setCurrencyPropertyPath('currency');

        $orderTotal = MoneyField::new('orderTotalTtcCents', 'Total TTC')
            ->setStoredAsCents(true)
            ->setCurrencyPropertyPath('currency');

        $currency = TextField::new('currency', 'Devise');

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
            FormField::addTab('Statuts'),
            $paymentStatus,
            $fulfillmentStatus,
            $userEmail,

            FormField::addTab('Adresses'),
            $shippingAddress,
            $billingAddress,

            FormField::addTab('Montants'),
            $itemsTotal,
            $taxAmount,
            $carrierPrice,
            $orderTotal,
            $currency,
            $weight,

            FormField::addTab('Transport & paiement'),
            $carrierName,
            $paymentMethodName,
            $paymentReference,
            $orderReference,
            $paidAt,
            

            FormField::addTab('Tech'),
            $cartClearedAt,
        ];
    }

    private function paymentChoices(): array
    {
        return [
            PaymentStatus::Pending->label() => PaymentStatus::Pending,
            PaymentStatus::Paid->label() => PaymentStatus::Paid,
            PaymentStatus::Refunded->label() => PaymentStatus::Refunded,
            PaymentStatus::Failed->label() => PaymentStatus::Failed,
        ];
    }

    private function fulfillmentChoices(): array
    {
        return [
            FulfillmentStatus::Draft->label() => FulfillmentStatus::Draft,
            FulfillmentStatus::Preparing->label() => FulfillmentStatus::Preparing,
            FulfillmentStatus::Shipped->label() => FulfillmentStatus::Shipped,
            FulfillmentStatus::Delivered->label() => FulfillmentStatus::Delivered,
            FulfillmentStatus::Cancelled->label() => FulfillmentStatus::Cancelled,
        ];
    }

    private function paymentBadges(): array
    {
        return [
            PaymentStatus::Pending->value => 'warning',
            PaymentStatus::Paid->value => 'success',
            PaymentStatus::Refunded->value => 'danger',
            PaymentStatus::Failed->value => 'danger',
        ];
    }

    private function fulfillmentBadges(): array
    {
        return [
            FulfillmentStatus::Draft->value => 'secondary',
            FulfillmentStatus::Preparing->value => 'info',
            FulfillmentStatus::Shipped->value => 'primary',
            FulfillmentStatus::Delivered->value => 'success',
            FulfillmentStatus::Cancelled->value => 'danger',
        ];
    }
}
