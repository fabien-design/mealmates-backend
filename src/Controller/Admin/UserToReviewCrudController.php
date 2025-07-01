<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enums\UserStatus;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserToReviewCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs à modérer')
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('first_name', 'Prénom')->hideOnForm(),
            TextField::new('last_name', 'Nom')->hideOnForm(),
            EmailField::new('email', 'Email')->hideOnForm(),
            TextField::new('stripeAccountId', 'Compte Stripe')->hideOnForm()
                ->hideOnForm()
                ->setColumns(3),
            BooleanField::new('isVerified', 'Vérifié')
                ->hideOnForm(),
            ArrayField::new('roles', 'Rôles')->hideOnForm()
                ->hideOnIndex(),
            TextField::new('moderationComment', 'Commentaire de modération')
                ->setColumns(6)
                ->hideOnIndex(),
            DateTimeField::new('moderatedAt', 'Modéré le')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setColumns(2)
                ->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $approveAction = Action::new('approve', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('approve')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(static function ($entity) {
                return $entity->getStatus() === UserStatus::NEED_VERIFICATION;
            });

        $rejectAction = Action::new('reject', 'Rejeter', 'fa fa-times')
            ->linkToCrudAction('reject')
            ->setCssClass('btn btn-danger btn-sm')
            ->displayIf(static function ($entity) {
                return $entity->getStatus() === UserStatus::NEED_VERIFICATION;
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $approveAction)
            ->add(Crud::PAGE_INDEX, $rejectAction)
            ->disable(Action::NEW)
            ->disable(Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('first_name')
            ->add('last_name')
            ->add('email')
            ->add('isVerified');
    }

    public function approve(AdminContext $context): RedirectResponse
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        $user->setStatus(UserStatus::APPROVED);
        $user->setModeratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Utilisateur #%d approuvé avec succès', $user->getId()));

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function reject(AdminContext $context): RedirectResponse
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        $user->setStatus(UserStatus::REJECTED);
        $user->setModeratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Utilisateur #%d rejeté avec succès', $user->getId()));

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class
        ]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $queryBuilder->andWhere('entity.status = :status')
            ->setParameter('status', UserStatus::NEED_VERIFICATION);

        return $queryBuilder;
    }
}
