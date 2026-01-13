<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\PaymentStatus;
use App\Enum\FulfillmentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
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
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
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

        $paidAt = DateTimeField::new('paidAt', 'Payée le')->setRequired(false);
        $cartClearedAt = DateTimeField::new('cartClearedAt', 'Panier vidé le')->setRequired(false);

        if (Crud::PAGE_INDEX === $pageName) {
            return [
                $id,
                $paymentStatus,
                $fulfillmentStatus,
                $userEmail,
                TextField::new('shippingAddress', 'Livraison')->setMaxLength(80),
                $orderTotal,
                $paidAt,
                $paymentReference,
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

            FormField::addTab('Transport & paiement'),
            $carrierName,
            $paymentMethodName,
            $paymentReference,
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
