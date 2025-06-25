<?php

namespace App\Controller\Admin;

use App\Admin\Filters\SoldStatusFilter;
use App\Entity\Offer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

class OfferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Offer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Offre')
            ->setEntityLabelInPlural('Offres')
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
            TextField::new('name', 'Nom'),
            TextareaField::new('description', 'Description')
                ->setMaxLength(300)
                ->hideOnIndex(),
            NumberField::new('price', 'Prix')
              ->setNumDecimals(2)
              ->formatValue(function ($value) {
                if ($value == 0.0) {
                  return '<span class="badge badge-success">Don</span>';
                } 

                return number_format($value, 2, ',', ' ') . ' €';
              }),
            NumberField::new('quantity', 'Quantité'),
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
        ];
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
}
