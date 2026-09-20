<?php

namespace App\Admin;

use App\Entity\Application;
use App\Entity\User;
use App\Service\Admin\AdminSeasonContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminSeasonContext $seasonContext,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Пользователь')
            ->setEntityLabelInPlural('Пользователи')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $season = $this->seasonContext->getSelectedSeason();
        if ($season === null) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->innerJoin(Application::class, 'seasonApp', 'WITH', 'seasonApp.user = entity AND seasonApp.season = :season')
            ->setParameter('season', $season)
            ->distinct();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name')->setLabel('Имя');
        yield EmailField::new('email')->setLabel('Email');
        yield TelephoneField::new('phone')->setLabel('Телефон');
        yield DateTimeField::new('createdAt')->setLabel('Создан')->hideOnForm();
        yield DateTimeField::new('updatedAt')->setLabel('Обновлен')->hideOnForm();
    }
}
