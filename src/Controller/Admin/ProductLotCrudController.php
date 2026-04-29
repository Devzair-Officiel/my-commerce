<?php

namespace App\Controller\Admin;

use App\Entity\ProductLot;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use App\Repository\ProductLotRepository;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class ProductLotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductLot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Numéro de lot')
            ->setEntityLabelInPlural('Numéros de lot')
            ->setPageTitle(Crud::PAGE_INDEX, 'Historique des lots')
            ->setDefaultSort(['receivedAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('product', 'Produit')
            ->setColumns(6)
            ->autocomplete();
        yield TextField::new('lotNumber', 'N° de lot')
            ->setColumns(4)
            ->setHelp('Format : L-YE-JU-20260628');
        yield DateField::new('receivedAt', 'Date de réception')
            ->setColumns(3);
        yield DateField::new('expiresAt', 'Bon jusqu\'au')
            ->setColumns(3)
            ->setRequired(false);
        yield BooleanField::new('isCurrent', 'Lot en cours')
            ->renderAsSwitch(true)
            ->setHelp('Un seul lot actif par produit.');
        yield TextareaField::new('notes', 'Notes')
            ->setColumns(12)
            ->setRequired(false)
            ->onlyOnForms();
    }

    public function configureActions(Actions $actions): Actions
    {
        $setCurrent = Action::new('setCurrent', 'Définir comme actif', 'fa fa-check-circle')
            ->linkToCrudAction('setCurrentLot')
            ->displayIf(fn(ProductLot $lot) => !$lot->isCurrent());

        return $actions
            ->add(Crud::PAGE_INDEX, $setCurrent)
            ->reorder(Crud::PAGE_INDEX, ['setCurrent', Action::EDIT, Action::DELETE]);
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entityInstance): void
    {
        $this->enforceUniqueCurrent($entityInstance);
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entityInstance): void
    {
        $this->enforceUniqueCurrent($entityInstance);
        parent::updateEntity($em, $entityInstance);
    }

    private function enforceUniqueCurrent(mixed $lot): void
    {
        if (!$lot instanceof ProductLot || !$lot->isCurrent() || $lot->getProduct() === null) {
            return;
        }

        foreach ($lot->getProduct()->getLots() as $other) {
            if ($other !== $lot) {
                $other->setIsCurrent(false);
            }
        }
    }

    public function setCurrentLot(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $urlGenerator, ProductLotRepository $repo): RedirectResponse
    {
        $id = $context->getRequest()->query->getInt('entityId');
        $lot = $repo->find($id);

        if (!$lot instanceof ProductLot) {
            $this->addFlash('danger', 'Lot introuvable.');
            return new RedirectResponse($urlGenerator->setController(self::class)->setAction(Crud::PAGE_INDEX)->generateUrl());
        }

        foreach ($lot->getProduct()->getLots() as $other) {
            $other->setIsCurrent(false);
        }

        $lot->setIsCurrent(true);
        $em->flush();

        $this->addFlash('success', 'Lot ' . $lot->getLotNumber() . ' défini comme actif.');

        return new RedirectResponse(
            $urlGenerator->setController(self::class)->setAction(Crud::PAGE_INDEX)->generateUrl()
        );
    }
}
