<?php

namespace App\Controller\Admin;

use App\Entity\HoneyTasting;
use App\Repository\HoneyTastingRepository;
use App\Service\HoneyTastingPdfRenderer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Consultation des fiches de dégustation soumises via le formulaire public.
 * Vue en lecture seule + téléchargement d'une version PDF de la fiche.
 */
#[IsGranted('ROLE_ADMIN')]
final class HoneyTastingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly HoneyTastingPdfRenderer $pdfRenderer,
        private readonly HoneyTastingRepository  $repository,
    ) {}

    public static function getEntityFqcn(): string
    {
        return HoneyTasting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Fiche de dégustation')
            ->setEntityLabelInPlural('Fiches de dégustation')
            ->setPageTitle(Crud::PAGE_INDEX, 'Fiches de dégustation reçues')
            ->setPageTitle(Crud::PAGE_DETAIL, fn (HoneyTasting $t) => 'Fiche — ' . $t->getTasterName())
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['tasterName'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $downloadPdf = Action::new('downloadPdf', 'PDF', 'fa fa-download')
            ->linkToCrudAction('downloadPdf')
            ->addCssClass('btn btn-sm btn-secondary');

        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $downloadPdf)
            ->add(Crud::PAGE_DETAIL, $downloadPdf);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('tasterName', 'Dégustateur');

        yield IntegerField::new('descriptorsCount', 'Descripteurs')
            ->onlyOnIndex()
            ->formatValue(fn ($value, HoneyTasting $t) => (string) \count($t->getData()));

        yield DateTimeField::new('createdAt', 'Reçue le')->setFormat('dd/MM/yyyy HH:mm');

        // ArrayField (et non Field) : la propriété `data` est mappée en Types::JSON,
        // ce qui déclenche l'auto-remplacement d'un Field générique par un ArrayField
        // en interne. En le déclarant directement, on conserve notre templatePath custom.
        yield ArrayField::new('data', 'Évaluations')
            ->onlyOnDetail()
            ->setTemplatePath('admin/honey_tasting/_field_data.html.twig');
    }

    public function downloadPdf(AdminContext $context): Response
    {
        $id = $context->getRequest()->query->getInt('entityId');
        $tasting = $this->repository->find($id);

        if ($tasting === null) {
            throw new NotFoundHttpException('Fiche introuvable.');
        }

        $pdf      = $this->pdfRenderer->render($tasting);
        $filename = $this->pdfRenderer->getFilename($tasting);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
