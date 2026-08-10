<?php

namespace App\Admin;

use App\Entity\ParticipationOption;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ParticipationOptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParticipationOption::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Вариант участия')
            ->setEntityLabelInPlural('Варианты участия');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('product')->setLabel('Проект');
        yield TextField::new('code')->setLabel('Код')->hideOnForm()->hideOnIndex();
        yield TextField::new('name')->setLabel('Название');
    }
}
