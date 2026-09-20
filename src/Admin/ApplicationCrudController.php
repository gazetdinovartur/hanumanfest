<?php

namespace App\Admin;

use App\Entity\Application;
use App\Enum\ApplicationStatus;
use App\Service\Admin\AdminSeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ApplicationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminSeasonContext $seasonContext,
    ) {
    }

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

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $season = $this->seasonContext->getSelectedSeason();
        if ($season === null) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->andWhere('entity.season = :season')
            ->setParameter('season', $season);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Application && $entityInstance->getSeason() === null) {
            $entityInstance->setSeason($this->seasonContext->getSelectedSeason());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('uuid')->setLabel('UUID')->hideOnForm();
        yield AssociationField::new('user')->setLabel('Пользователь');
        yield AssociationField::new('season')->setLabel('Сезон')->hideOnForm();
        yield AssociationField::new('pricingPeriod')
            ->setLabel('Период стоимости')
            ->setQueryBuilder(function (QueryBuilder $qb): QueryBuilder {
                $season = $this->seasonContext->getSelectedSeason();
                if ($season !== null) {
                    $qb->andWhere('entity.season = :season')->setParameter('season', $season);
                }

                return $qb;
            });
        yield ChoiceField::new('status')
            ->setChoices([
                'Новая' => ApplicationStatus::New,
                'Частично оплачена' => ApplicationStatus::PartiallyPaid,
                'Оплачена' => ApplicationStatus::Paid,
                'Возврат' => ApplicationStatus::Refunded,
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
