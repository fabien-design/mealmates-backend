<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Entity\User;
use App\Enums\ReviewStatus;
use App\Repository\ReviewRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Doctrine\ORM\QueryBuilder;

class ReviewCrudController extends AbstractCrudController
{

  public function __construct(
    private EntityManagerInterface $entityManager,
    private readonly ReviewRepository $reviewRepository
  ) {}

  public static function getEntityFqcn(): string
  {
    return Review::class;
  }

  public function configureCrud(Crud $crud): Crud
  {
    return $crud
      ->setEntityLabelInSingular('Review')
      ->setEntityLabelInPlural('Reviews à modérer')
      ->setDefaultSort(['createdAt' => 'ASC'])
      ->setPaginatorPageSize(20);
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      IdField::new('id')->hideOnForm(),

      AssociationField::new('reviewer', 'Auteur')
        ->setColumns(2)
        ->hideOnForm()
        ->formatValue(function ($value) {
          return $value->getFullName() ?? 'N/A';
        }),

      AssociationField::new('reviewed', 'Évalué')
        ->setColumns(2)
        ->hideOnForm()
        ->formatValue(function ($value) {
          return $value->getFullName() ?? 'N/A';
        }),

      AssociationField::new('transaction', 'Offre')
        ->setColumns(3)
        ->hideOnForm()
        ->renderAsHtml()
        ->formatValue(function ($value, $entity) {
          $transaction = $entity->getTransaction();
          if (!$transaction) return 'N/A';
          $offer = $transaction->getOffer();
          if (!$offer) return 'Offre supprimée';

          $url = sprintf(
            '/admin/offer/%d',
            $offer->getId()
          );

          return sprintf(
            '<a href="%s">%s (%.2f€)</a>',
            $url,
            $offer->getName(),
            $offer->getPrice()
          );
        }),

      NumberField::new('productQualityRating', 'Qualité')
        ->setNumDecimals(1)
        ->setColumns(1)
        ->hideOnForm(),

      NumberField::new('appointmentRespectRating', 'Ponctualité')
        ->setNumDecimals(1)
        ->setColumns(1)
        ->hideOnForm(),

      NumberField::new('friendlinessRating', 'Amabilité')
        ->setNumDecimals(1)
        ->setColumns(1)
        ->hideOnForm(),

      NumberField::new('averageRating', 'Moyenne')
        ->setNumDecimals(1)
        ->setColumns(1)
        ->hideOnForm()
        ->onlyOnIndex(),

      TextField::new('moderationComment', 'Commentaire de modération')
        ->setColumns(6)
        ->hideOnIndex(),

      DateTimeField::new('createdAt', 'Créé le')
        ->setFormat('dd/MM/yyyy HH:mm')
        ->setColumns(2)
        ->hideOnForm(),

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
        return $entity->getStatus() === ReviewStatus::NEED_VERIFICATION;
      });

    $rejectAction = Action::new('reject', 'Rejeter', 'fa fa-times')
      ->linkToCrudAction('reject')
      ->setCssClass('btn btn-danger btn-sm')
      ->displayIf(static function ($entity) {
        return $entity->getStatus() === ReviewStatus::NEED_VERIFICATION;
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
      ->add('productQualityRating')
      ->add('appointmentRespectRating')
      ->add('friendlinessRating')
      ->add('createdAt')
      ->add('reviewer')
      ->add('reviewed');
  }

  public function approve(AdminContext $context): RedirectResponse
  {
    /** @var Review $review */
    $review = $context->getEntity()->getInstance();

    $review->setStatus(ReviewStatus::APPROVED);
    $review->setModeratedAt(new \DateTimeImmutable());

    $this->entityManager->flush();

    $this->updateUserRating($review->getReviewed());

    $this->addFlash('success', sprintf('Review #%d approuvée avec succès', $review->getId()));

    return $this->redirectToRoute('admin', [
      'crudAction' => 'index',
      'crudControllerFqcn' => self::class
    ]);
  }

  public function reject(AdminContext $context): RedirectResponse
  {
    /** @var Review $review */
    $review = $context->getEntity()->getInstance();

    $review->setStatus(ReviewStatus::REJECTED);
    $review->setModeratedAt(new \DateTimeImmutable());

    $this->entityManager->flush();

    $this->addFlash('success', sprintf('Review #%d rejetée avec succès', $review->getId()));

    return $this->redirectToRoute('admin', [
      'crudAction' => 'index',
      'crudControllerFqcn' => self::class
    ]);
  }

  private function updateUserRating(User $user): void
  {
    $ratings = $this->reviewRepository->findAverageRatingsForUser($user);

    $user->setAverageRating($ratings['avgOverall']);
    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }

  public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
  {
    $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
    
    $queryBuilder->andWhere('entity.status = :status')
                 ->setParameter('status', ReviewStatus::NEED_VERIFICATION);
    
    return $queryBuilder;
  }
}
