<?php

namespace App\Admin;

use App\Entity\Application;
use App\Enum\ApplicationStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class ApplicationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Application::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заявка')
            ->setEntityLabelInPlural('Заявки')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Заявки')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Заявка')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('uuid')->setLabel('UUID')->hideOnForm();
        yield AssociationField::new('user')->setLabel('Пользователь');
        yield AssociationField::new('pricingPeriod')->setLabel('Период стоимости');
        yield ChoiceField::new('status')
            ->setChoices([
                'Новая' => ApplicationStatus::New,
                'Частично оплачена' => ApplicationStatus::PartiallyPaid,
                'Оплачена' => ApplicationStatus::Paid,
                'Отменена' => ApplicationStatus::Cancelled,
            ])->setLabel('Статус');
        yield IntegerField::new('totalAmount')->setLabel('Итого (₽)');
        yield IntegerField::new('paidAmount')->setLabel('Оплачено (₽)');
        yield TextField::new('payload')
            ->setLabel('Данные формы')
            ->onlyOnDetail()
            ->formatValue(static fn ($value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        yield DateTimeField::new('createdAt')->setLabel('Создана')->hideOnForm();
        yield DateTimeField::new('updatedAt')->setLabel('Обновлена')->hideOnForm();
    }
}
