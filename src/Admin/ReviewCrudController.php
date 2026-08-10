<?php

namespace App\Admin;

use App\Admin\Field\PublicImageField;
use App\Entity\Review;
use App\Service\Content\UploadPathNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ReviewCrudController extends AbstractSortableUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($uploadPathNormalizer, $csrfTokenManager);
    }

    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->applySortableCrudDefaults($crud)
            ->setEntityLabelInSingular('Отзыв')
            ->setEntityLabelInPlural('Отзывы')
            ->setPageTitle(Crud::PAGE_INDEX, 'Отзывы')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый отзыв')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать отзыв');
    }

    protected function sortableKind(): string
    {
        return 'review';
    }

    protected function uploadPathMap(): array
    {
        return ['photoPath' => 'reviews'];
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', ' ')
                ->setTemplatePath('admin/field/drag.html.twig')
                ->setSortable(false);
            yield PublicImageField::thumbnail('photoPath', 'reviews');
            yield TextField::new('authorName', 'Автор');
            yield BooleanField::new('published', 'На сайте');

            return;
        }

        yield FormField::addFieldset('Отзыв');
        yield TextField::new('authorName', 'Автор');
        yield TextareaField::new('body', 'Текст')
            ->setHelp('HTML без комментариев WordPress.')
            ->setNumOfRows(6);
        yield PublicImageField::new('photoPath', 'Фото', 'reviews');
        yield BooleanField::new('published', 'Опубликовано');
    }
}
