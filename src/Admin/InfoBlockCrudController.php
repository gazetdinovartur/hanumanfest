<?php

namespace App\Admin;

use App\Admin\Field\PublicImageField;
use App\Entity\InfoBlock;
use App\Service\Content\UploadPathNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class InfoBlockCrudController extends AbstractSortableUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($uploadPathNormalizer, $csrfTokenManager);
    }

    public static function getEntityFqcn(): string
    {
        return InfoBlock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->applySortableCrudDefaults($crud)
            ->setEntityLabelInSingular('Инфоблок')
            ->setEntityLabelInPlural('Инфоблоки')
            ->setPageTitle(Crud::PAGE_INDEX, 'Инфоблоки')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый инфоблок')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать инфоблок');
    }

    protected function sortableKind(): string
    {
        return 'info';
    }

    protected function uploadPathMap(): array
    {
        return ['imagePath' => 'info'];
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', ' ')
                ->setTemplatePath('admin/field/drag.html.twig')
                ->setSortable(false);
            yield PublicImageField::thumbnail('imagePath', 'info');
            yield TextField::new('title', 'Заголовок');
            yield BooleanField::new('published', 'На сайте');

            return;
        }

        yield FormField::addFieldset('Контент');
        yield TextField::new('title', 'Заголовок');
        yield TextareaField::new('content', 'Текст')
            ->setHelp('HTML без комментариев WordPress.')
            ->setNumOfRows(6);
        yield PublicImageField::new('imagePath', 'Изображение', 'info');
        yield BooleanField::new('published', 'Опубликовано');
    }
}
