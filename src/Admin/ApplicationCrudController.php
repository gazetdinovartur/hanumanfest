<?php

namespace App\Admin;

use App\Entity\Application;
use App\Service\Admin\AdminSeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class ApplicationCrudController extends AbstractCrudController
{
    use ReadOnlyCrudTrait;

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
            ->setPageTitle(Crud::PAGE_DETAIL, static function (Application $application): string {
                $name = trim((string) ($application->getUser()?->getName() ?? ''));

                return $name !== '' ? $name : 'Заявка';
            })
            ->setSearchFields(['uuid', 'user.name', 'user.email', 'user.phone'])
            ->setDefaultRowAction(Action::DETAIL)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureReadOnlyActions($actions);
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
        yield AssociationField::new('user')
            ->setLabel('Пользователь')
            ->setCrudController(UserCrudController::class)
            ->hideOnDetail();
        yield ChoiceField::new('status')->setLabel('Статус')->hideOnDetail();
        yield IntegerField::new('totalAmount')->setLabel('Итого (₽)')->hideOnDetail();
        yield IntegerField::new('paidAmount')->setLabel('Оплачено (₽)')->hideOnDetail();
        yield BooleanField::new('isTest', 'Тест')->renderAsSwitch(false)->hideOnForm()->hideOnDetail();
        yield DateTimeField::new('createdAt')->setLabel('Создана')->hideOnForm()->hideOnDetail()->setFormat('dd.MM.yyyy HH:mm');

        yield ArrayField::new('readableDetails')
            ->setLabel('Данные формы')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/definition_list.html.twig');
        yield AssociationField::new('payments')
            ->setLabel('Платежи')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/related_entities.html.twig')
            ->setCustomOption('crudController', PaymentCrudController::class);
        yield AssociationField::new('paymentLinks')
            ->setLabel('Ссылки на оплату')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/payment_links.html.twig');
    }
}
