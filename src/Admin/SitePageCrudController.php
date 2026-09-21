<?php

namespace App\Admin;

use App\Admin\Field\HtmlEditorField;
use App\Admin\Field\PublicVideoField;
use App\Entity\SitePage;
use App\Enum\SitePageTemplate;
use App\Service\Content\UploadPathNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SitePageCrudController extends AbstractUploadCrudController
{
    public function __construct(UploadPathNormalizer $uploadPathNormalizer)
    {
        parent::__construct($uploadPathNormalizer);
    }

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

    protected function uploadPathMap(): array
    {
        return [
            'kitchenVideo1' => 'pages/kitchen',
            'kitchenVideo2' => 'pages/kitchen',
            'kitchenVideo3' => 'pages/kitchen',
            'kitchenVideo4' => 'pages/kitchen',
        ];
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
            ->renderAsNativeWidget()
            ->setFormTypeOption(
                'choice_value',
                static function (mixed $value): string {
                    return $value instanceof SitePageTemplate ? $value->value : (string) $value;
                },
            )
            ->setFormTypeOption('attr', ['data-hf-page-template' => '1']);
        yield HtmlEditorField::new('contentHtml', 'Содержимое', 18);
        $videoFieldset = FormField::addFieldset('Видео')
            ->addCssClass('hf-kitchen-videos')
            ->onlyOnForms();
        $page = $this->getContext()?->getEntity()?->getInstance();
        if ($page instanceof SitePage && $page->getTemplate() === SitePageTemplate::Kitchen) {
            $videoFieldset->addCssClass('is-visible');
        }
        yield $videoFieldset;
        yield PublicVideoField::new('kitchenVideo1', 'Видео 1', 'pages/kitchen')->onlyOnForms();
        yield PublicVideoField::new('kitchenVideo2', 'Видео 2', 'pages/kitchen')->onlyOnForms();
        yield PublicVideoField::new('kitchenVideo3', 'Видео 3', 'pages/kitchen')->onlyOnForms();
        yield PublicVideoField::new('kitchenVideo4', 'Видео 4', 'pages/kitchen')->onlyOnForms();
        yield FormField::addFieldset('Публикация');
        yield IntegerField::new('sortOrder', 'Порядок в футере');
        yield BooleanField::new('showInFooter', 'Показывать в меню футера');
        yield BooleanField::new('published', 'Опубликовано');
    }
}
