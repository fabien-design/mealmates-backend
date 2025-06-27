<?php

namespace App\Admin\Filters;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\QueryBuilder;

final class SoldStatusFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions([
                'choices' => [
                    'Vendu' => 'sold',
                    'Non vendu' => 'not_sold',
                ],
                'placeholder' => 'Tous',
                'required' => false,
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $value = $filterDataDto->getValue();

        if ($value === 'sold') {
            $queryBuilder->andWhere($alias . '.soldAt IS NOT NULL');
        } elseif ($value === 'not_sold') {
            $queryBuilder->andWhere($alias . '.soldAt IS NULL');
        }
    }
}
