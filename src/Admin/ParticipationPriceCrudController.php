<?php

namespace App\Admin;

use App\Entity\ParticipationPrice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class ParticipationPriceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParticipationPrice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Цена участия')
            ->setEntityLabelInPlural('Цены участия')
            ->setDefaultSort(['pricingPeriod' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('pricingPeriod')->setLabel('Период стоимости');
        yield AssociationField::new('participationOption')->setLabel('Вариант участия');
        yield IntegerField::new('price')->setLabel('Цена (₽)');
    }
}
