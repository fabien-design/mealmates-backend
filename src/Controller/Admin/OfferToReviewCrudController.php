<?php

namespace App\Controller\Admin;

use App\Admin\Filters\SoldStatusFilter;
use App\Entity\Offer;
use App\Enums\OfferReportStatus;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OfferToReviewCrudController extends AbstractCrudController
{
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }

  public static function getEntityFqcn(): string
  {
    return Offer::class;
  }

  public function configureCrud(Crud $crud): Crud
  {
    return $crud
      ->setEntityLabelInSingular('Offre')
      ->setEntityLabelInPlural('Offres à modérer')
      ->setDefaultSort(['id' => 'DESC'])
      ->setPaginatorPageSize(25);
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      IdField::new('id')->hideOnForm(),
      DateTimeField::new('soldAt', 'Vendu')
        ->hideOnForm()
        ->formatValue(function ($value) {
          return $value ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-secondary">Non</span>';
        }),
      TextField::new('name', 'Nom')->hideOnForm(),
      TextareaField::new('description', 'Description')
        ->setMaxLength(300)
        ->hideOnIndex()->hideOnForm(),
      NumberField::new('price', 'Prix')
        ->setNumDecimals(2)
        ->formatValue(function ($value) {
          if ($value == 0.0) {
            return '<span class="badge badge-success">Don</span>';
          }
          return number_format($value, 2, ',', ' ') . ' €';
        })->hideOnForm(),
      NumberField::new('quantity', 'Quantité')->hideOnForm(),
      AssociationField::new('seller', 'Vendeur')
        ->hideOnForm(),
      DateTimeField::new('expiryDate', 'Expire le')
        ->setFormat('dd/MM/yyyy')
        ->formatValue(function ($value) {
          $yesterday = (new \DateTime('-1 day'))->setTime(0, 0, 0);
          if ($value === null || $value <= $yesterday) {
            return '<span class="badge badge-danger">Expirée</span>';
          }
          return $value->format('d/m/Y');
        })
        ->hideOnForm(),
      TextareaField::new('moderationComment', 'Commentaire de modération')
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
        return $entity->getReportStatus() === OfferReportStatus::NEED_VERIFICATION;
      });

    $rejectAction = Action::new('reject', 'Rejeter', 'fa fa-times')
      ->linkToCrudAction('reject')
      ->setCssClass('btn btn-danger btn-sm')
      ->displayIf(static function ($entity) {
        return $entity->getReportStatus() === OfferReportStatus::NEED_VERIFICATION;
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
      ->add(SoldStatusFilter::new('soldAt', 'Statut'))
      ->add('name')
      ->add('price')
      ->add('quantity')
      ->add('seller')
      ->add('expiryDate');
  }

  public function approve(AdminContext $context): RedirectResponse
  {
    /** @var Offer $offer */
    $offer = $context->getEntity()->getInstance();

    $offer->setReportStatus(OfferReportStatus::APPROVED);
    $offer->setModeratedAt(new \DateTimeImmutable());

    $this->entityManager->flush();

    $this->addFlash('success', sprintf('Offre #%d approuvée avec succès', $offer->getId()));

    return $this->redirectToRoute('admin', [
      'crudAction' => 'index',
      'crudControllerFqcn' => self::class
    ]);
  }

  public function reject(AdminContext $context): RedirectResponse
  {
    /** @var Offer $offer */
    $offer = $context->getEntity()->getInstance();

    $offer->setReportStatus(OfferReportStatus::REJECTED);
    $offer->setModeratedAt(new \DateTimeImmutable());

    $this->entityManager->flush();

    $this->addFlash('success', sprintf('Offre #%d rejetée avec succès', $offer->getId()));

    return $this->redirectToRoute('admin', [
      'crudAction' => 'index',
      'crudControllerFqcn' => self::class
    ]);
  }

  public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
  {
    $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

    $queryBuilder->andWhere('entity.status = :status')
      ->setParameter('status', OfferReportStatus::NEED_VERIFICATION);

    return $queryBuilder;
  }
}
