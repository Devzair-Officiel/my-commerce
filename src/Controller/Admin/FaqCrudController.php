<?php

namespace App\Controller\Admin;

use App\Entity\Faq;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FaqCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Faq::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Question FAQ')
            ->setEntityLabelInPlural('FAQ')
            ->setPageTitle(Crud::PAGE_INDEX, 'Gestion de la FAQ')
            ->setDefaultSort(['position' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')
            ->setColumns(2)
            ->setHelp('0 = en premier');
        yield TextField::new('question', 'Question')
            ->setColumns(10);
        yield TextareaField::new('answer', 'Réponse')
            ->setColumns(12)
            ->setFormTypeOption('attr', ['rows' => 4]);
        yield ChoiceField::new('section', 'Section')
            ->setChoices([
                'Choisir un miel' => 'Choisir un miel',
                'Origine, provenance et confiance' => 'Origine, provenance et confiance',
                'Analyses et lecture des fiches produit' => 'Analyses et lecture des fiches produit',
                'Cristallisation, texture et conservation' => 'Cristallisation, texture et conservation',
                'Usage, dégustation et habitudes' => 'Usage, dégustation et habitudes',
                'Commande, livraison et accompagnement' => 'Commande, livraison et accompagnement',
            ])
            ->setRequired(false)
            ->allowMultipleChoices(false)
            ->setColumns(6)
            ->setHelp('Laisser vide pour ne pas grouper cette question.');
        yield BooleanField::new('isActive', 'Visible')
            ->renderAsSwitch(true);
        yield BooleanField::new('isWholesale', 'Page grossiste')
            ->renderAsSwitch(true)
            ->setHelp('Activé → affiché sur /grossiste-miel. Désactivé → affiché sur /faq-miel.');
    }
}
