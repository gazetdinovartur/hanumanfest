<?php

namespace App\Admin;

use App\Entity\SitePage;
use App\Enum\SitePageTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SitePageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SitePage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Страница')
            ->setEntityLabelInPlural('Страницы')
            ->setPageTitle(Crud::PAGE_INDEX, 'Страницы')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая страница')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать страницу')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->setSearchFields(['title', 'slug']);
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id')->hideOnForm();
            yield TextField::new('title', 'Заголовок');
            yield TextField::new('slug', 'URL');
            yield ChoiceField::new('template', 'Шаблон')
                ->setChoices([
                    'Обычная' => SitePageTemplate::Default,
                    'Кухня / питание (с видео)' => SitePageTemplate::Kitchen,
                ]);
            yield BooleanField::new('showInFooter', 'В футере');
            yield IntegerField::new('sortOrder', 'Порядок');
            yield BooleanField::new('published', 'На сайте');

            return;
        }

        yield FormField::addFieldset('Страница');
        yield TextField::new('title', 'Заголовок')->setRequired(true);
        yield TextField::new('slug', 'URL (slug)')
            ->setHelp('Как на WP, например: питание-на-хануман-фест или политика-возвратов')
            ->setRequired(true);
        yield ChoiceField::new('template', 'Шаблон')
            ->setChoices([
                'Обычная' => SitePageTemplate::Default,
                'Кухня / питание (с видео)' => SitePageTemplate::Kitchen,
            ])
            ->renderAsNativeWidget();
        yield TextareaField::new('contentHtml', 'Содержимое (HTML)')
            ->setNumOfRows(18)
            ->setHelp('HTML без комментариев WordPress.');
        yield IntegerField::new('sortOrder', 'Порядок в футере');
        yield BooleanField::new('showInFooter', 'Показывать в меню футера');
        yield BooleanField::new('published', 'Опубликовано');
    }
}
