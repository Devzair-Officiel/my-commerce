<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            ChoiceField::new('status')
                // options = ['Label' => EnumCase]
                ->setChoices($this->orderStatusChoices())
                // affichage sur l’index
                ->renderAsBadges([
                    OrderStatus::Draft->value => 'secondary',
                    OrderStatus::PendingPayment->value => 'warning',
                    OrderStatus::Paid->value => 'success',
                    OrderStatus::Preparing->value => 'info',
                    OrderStatus::Shipped->value => 'primary',
                    OrderStatus::Delivered->value => 'success',
                    OrderStatus::Cancelled->value => 'danger',
                    OrderStatus::Refunded->value => 'danger',
                ]),
        ];
    }

    private function orderStatusChoices(): array
    {
        // Labels "propres" (tu peux adapter)
        return [
            'Brouillon' => OrderStatus::Draft,
            'En attente paiement' => OrderStatus::PendingPayment,
            'Payée' => OrderStatus::Paid,
            'Préparation' => OrderStatus::Preparing,
            'Expédiée' => OrderStatus::Shipped,
            'Livrée' => OrderStatus::Delivered,
            'Annulée' => OrderStatus::Cancelled,
            'Remboursée' => OrderStatus::Refunded,
        ];
    }
}
