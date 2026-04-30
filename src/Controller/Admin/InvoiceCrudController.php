<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\InvoicePdfRenderer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur EasyAdmin pour la consultation et le téléchargement PDF des factures générées automatiquement.
 */
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class InvoiceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly InvoicePdfRenderer $pdfRenderer,
        private readonly InvoiceRepository  $invoiceRepository,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Invoice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Facture')
            ->setEntityLabelInPlural('Factures')
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des factures')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détail de la facture')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier la facture')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['invoiceNumber', 'customerOrder.orderReference']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $downloadPdf = Action::new('downloadPdf', 'PDF', 'fa fa-download')
            ->linkToCrudAction('downloadPdf')
            ->addCssClass('btn btn-sm btn-secondary')
            ->displayIf(static fn(Invoice $i) => $i->isIssued());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $downloadPdf)
            ->add(Crud::PAGE_DETAIL, $downloadPdf)
            // Une facture émise ne peut plus être supprimée
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            // Désactiver NEW depuis l'admin (créées automatiquement au paiement)
            ->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'Brouillon' => InvoiceStatus::Draft->value,
                'Émise'     => InvoiceStatus::Issued->value,
                'Annulée'   => InvoiceStatus::Cancelled->value,
            ]))
            ->add(DateTimeFilter::new('issuedAt', 'Date d\'émission'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'));
    }

    public function configureFields(string $pageName): iterable
    {
        // Les valeurs doivent être des instances d'enum, pas des strings,
        // pour que Symfony puisse les passer directement au setter setStatus(InvoiceStatus)
        $statusChoices = [
            'Brouillon' => InvoiceStatus::Draft,
            'Émise'     => InvoiceStatus::Issued,
            'Annulée'   => InvoiceStatus::Cancelled,
        ];

        $statusBadgeMap = [
            InvoiceStatus::Draft->value     => 'secondary',
            InvoiceStatus::Issued->value    => 'success',
            InvoiceStatus::Cancelled->value => 'danger',
        ];

        if (Crud::PAGE_INDEX === $pageName) {
            return [
                TextField::new('invoiceNumber', 'N° facture'),
                AssociationField::new('customerOrder', 'Commande'),
                ChoiceField::new('status', 'Statut')
                    ->setChoices($statusChoices)
                    ->renderAsBadges($statusBadgeMap),
                DateTimeField::new('issuedAt', 'Date d\'émission')->setFormat('dd/MM/yyyy'),
                DateTimeField::new('createdAt', 'Créée le')->setFormat('dd/MM/yyyy'),
            ];
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                TextField::new('invoiceNumber', 'N° facture'),
                AssociationField::new('customerOrder', 'Commande'),
                ChoiceField::new('status', 'Statut')
                    ->setChoices($statusChoices)
                    ->renderAsBadges($statusBadgeMap),
                DateTimeField::new('issuedAt', 'Date d\'émission')->setFormat('dd/MM/yyyy HH:mm'),
                DateTimeField::new('createdAt', 'Créée le')->setFormat('dd/MM/yyyy HH:mm'),
                TextareaField::new('notes', 'Notes / Mentions légales'),
            ];
        }

        // PAGE_EDIT — seuls les champs modifiables quand brouillon
        return [
            FormField::addFieldset('Statut'),
            ChoiceField::new('status', 'Statut')
                ->setChoices($statusChoices)
                ->setHelp('⚠️ Une facture émise ne doit pas être rétrogradée en brouillon'),

            FormField::addFieldset('Notes et mentions légales'),
            TextareaField::new('notes', 'Notes / Mentions légales')
                ->setColumns(12)
                ->setFormTypeOption('attr', ['rows' => 6])
                ->setHelp('Apparaît en bas du PDF. Modifiable uniquement avant émission.'),
        ];
    }

    public function downloadPdf(AdminContext $context): Response
    {
        $id = $context->getRequest()->query->getInt('entityId');
        $invoice = $this->invoiceRepository->find($id);

        if ($invoice === null) {
            throw new NotFoundHttpException("Facture introuvable.");
        }

        $pdf      = $this->pdfRenderer->render($invoice);
        $filename = $this->pdfRenderer->getFilename($invoice);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
