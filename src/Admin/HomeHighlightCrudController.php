<?php

namespace App\Admin;

use App\Entity\HomeHighlight;
use App\Enum\HomeHighlightColumn;
use App\Enum\HomeHighlightStyle;
use App\Service\Content\UploadPathNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class HomeHighlightCrudController extends AbstractSortableUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($uploadPathNormalizer, $csrfTokenManager);
    }

    public static function getEntityFqcn(): string
    {
        return HomeHighlight::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->applySortableCrudDefaults($crud)
            ->setEntityLabelInSingular('Плитка')
            ->setEntityLabelInPlural('Плитки «О фестивале»')
            ->setPageTitle(Crud::PAGE_INDEX, 'Плитки «О фестивале»')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая плитка')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать плитку');
    }

    protected function sortableKind(): string
    {
        return 'highlight';
    }

    protected function uploadPathMap(): array
    {
        return [];
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', ' ')
                ->setTemplatePath('admin/field/drag.html.twig')
                ->setSortable(false);
            yield TextareaField::new('text', 'Текст');
            yield ChoiceField::new('columnSide', 'Колонка')
                ->setChoices([
                    'Слева' => HomeHighlightColumn::Left,
                    'Справа' => HomeHighlightColumn::Right,
                ]);
            yield BooleanField::new('published', 'На сайте');

            return;
        }

        yield FormField::addFieldset('Плитка');
        yield TextareaField::new('text', 'Текст')->setNumOfRows(3)->setRequired(true);
        yield ChoiceField::new('columnSide', 'Колонка')
            ->setChoices([
                'Слева (широкие)' => HomeHighlightColumn::Left,
                'Справа' => HomeHighlightColumn::Right,
            ])
            ->renderAsNativeWidget();
        yield ChoiceField::new('style', 'Размер')
            ->setChoices([
                'Большая (слева)' => HomeHighlightStyle::Big,
                'Обычная' => HomeHighlightStyle::Normal,
                'Широкая (справа)' => HomeHighlightStyle::Wide,
            ])
            ->renderAsNativeWidget();
        yield IntegerField::new('sortOrder', 'Порядок');
        yield BooleanField::new('published', 'Опубликовано');
    }
}
