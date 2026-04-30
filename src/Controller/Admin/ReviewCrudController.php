<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Enum\ReviewStatus;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur EasyAdmin pour la modération des avis clients.
 */
#[IsGranted('ROLE_ADMIN')]
class ReviewCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Avis client')
            ->setEntityLabelInPlural('Avis clients')
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des avis')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['comment', 'user.email'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approve', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('approveReview')
            ->addCssClass('btn btn-sm btn-success')
            ->displayIf(fn(Review $r) => $r->getStatus() !== ReviewStatus::Approved);

        $reject = Action::new('reject', 'Rejeter', 'fa fa-times')
            ->linkToCrudAction('rejectReview')
            ->addCssClass('btn btn-sm btn-danger')
            ->displayIf(fn(Review $r) => $r->getStatus() !== ReviewStatus::Rejected);

        $reply = Action::new('reply', 'Répondre', 'fa fa-reply')
            ->linkToCrudAction(Action::EDIT)
            ->addCssClass('btn btn-sm btn-outline-primary')
            ->displayIf(fn(Review $r) => !$r->hasAdminReply());

        $editReply = Action::new('editReply', 'Modifier la réponse', 'fa fa-pen')
            ->linkToCrudAction(Action::EDIT)
            ->addCssClass('btn btn-sm btn-outline-secondary')
            ->displayIf(fn(Review $r) => $r->hasAdminReply());

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_INDEX, $reply)
            ->add(Crud::PAGE_INDEX, $editReply)
            ->add(Crud::PAGE_DETAIL, $approve)
            ->add(Crud::PAGE_DETAIL, $reject);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(
            ChoiceFilter::new('status', 'Statut')->setChoices([
                'En attente' => ReviewStatus::Pending->value,
                'Approuvé'   => ReviewStatus::Approved->value,
                'Rejeté'     => ReviewStatus::Rejected->value,
            ])
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield AssociationField::new('product', 'Produit');

        yield AssociationField::new('user', 'Client');

        yield Field::new('rating', 'Note')
            ->formatValue(function ($value) {
                $stars = str_repeat('★', (int) $value) . str_repeat('☆', 5 - (int) $value);
                return sprintf(
                    '<span title="%d/5" style="color:#e37e03;font-size:1.1em;">%s</span> <span class="badge bg-secondary">%d/5</span>',
                    $value,
                    $stars,
                    $value,
                );
            });

        yield TextareaField::new('comment', 'Commentaire')
            ->setMaxLength(200);

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'En attente' => ReviewStatus::Pending,
                'Approuvé'   => ReviewStatus::Approved,
                'Rejeté'     => ReviewStatus::Rejected,
            ])
            ->renderAsBadges([
                ReviewStatus::Pending->value  => 'warning',
                ReviewStatus::Approved->value => 'success',
                ReviewStatus::Rejected->value => 'danger',
            ])
            ->onlyOnIndex();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'En attente' => ReviewStatus::Pending,
                'Approuvé'   => ReviewStatus::Approved,
                'Rejeté'     => ReviewStatus::Rejected,
            ])
            ->renderAsBadges([
                ReviewStatus::Pending->value  => 'warning',
                ReviewStatus::Approved->value => 'success',
                ReviewStatus::Rejected->value => 'danger',
            ])
            ->hideWhenCreating()
            ->onlyOnDetail();

        yield DateTimeField::new('createdAt', 'Date')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->onlyOnIndex();

        yield TextareaField::new('adminReply', 'Réponse admin')
            ->setHelp('Cette réponse sera visible publiquement sous l\'avis client.')
            ->setFormTypeOption('attr', ['rows' => 5])
            ->hideOnIndex();

        yield DateTimeField::new('adminRepliedAt', 'Répondu le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function approveReview(AdminContext $context): RedirectResponse
    {
        $review = $this->em->getRepository(Review::class)->find(
            $context->getRequest()->query->getInt('entityId')
        );

        if ($review) {
            $review->setStatus(ReviewStatus::Approved);
            $this->em->flush();
            $this->addFlash('success', 'L\'avis a été approuvé.');
        }

        return $this->redirect(
            $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }

    public function rejectReview(AdminContext $context): RedirectResponse
    {
        $review = $this->em->getRepository(Review::class)->find(
            $context->getRequest()->query->getInt('entityId')
        );

        if ($review) {
            $review->setStatus(ReviewStatus::Rejected);
            $this->em->flush();
            $this->addFlash('success', 'L\'avis a été rejeté.');
        }

        return $this->redirect(
            $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }
}
