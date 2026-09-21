<?php

namespace App\Admin;

use App\Admin\Field\HtmlEditorField;
use App\Admin\Field\PublicImageField;
use App\Entity\Review;
use App\Service\Content\UploadPathNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ReviewCrudController extends AbstractSortableUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        private readonly CsrfTokenManagerInterface $mediaCsrf,
    ) {
        parent::__construct($uploadPathNormalizer, $mediaCsrf);
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
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать отзыв')
            ->overrideTemplate('crud/edit', 'admin/review/edit.html.twig');
    }

    public function configureAssets(Assets $assets): Assets
    {
        $assets = parent::configureAssets($assets);
        $token = $this->mediaCsrf->getToken('review_media_admin')->getValue();

        return $assets
            ->addCssFile('css/admin-gallery.css')
            ->addCssFile('css/admin-review-media.css')
            ->addHtmlContentToBody(sprintf(
                '<meta name="hf-review-media-csrf" content="%s">',
                htmlspecialchars($token, ENT_QUOTES),
            ));
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);
        if (Crud::PAGE_EDIT === $responseParameters->get('pageName')) {
            $responseParameters->set('reviewMediaCsrf', $this->mediaCsrf->getToken('review_media_admin')->getValue());
        }

        return $responseParameters;
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
        yield PublicImageField::new('photoPath', 'Портрет', 'reviews')
            ->setHelp('Фото автора для карточки и шапки модалки');
        yield TextField::new('authorName', 'Автор');
        yield HtmlEditorField::new('body', 'Текст', 6)
            ->setHelp('Только текст. Фото и видео с фестиваля — в блоке «Медиа отзыва» ниже (после сохранения).');
        yield BooleanField::new('published', 'Опубликовано');
    }
}
