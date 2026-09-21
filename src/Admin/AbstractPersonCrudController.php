<?php

namespace App\Admin;

use App\Admin\Field\HtmlEditorField;
use App\Admin\Field\PublicImageField;
use App\Entity\Person;
use App\Enum\PersonKind;
use App\Service\Content\UploadPathNormalizer;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

abstract class AbstractPersonCrudController extends AbstractSortableUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($uploadPathNormalizer, $csrfTokenManager);
    }

    public static function getEntityFqcn(): string
    {
        return Person::class;
    }

    protected function uploadPathMap(): array
    {
        return ['photoPath' => 'people'];
    }

    /** @return list<PersonKind> */
    abstract protected function personKinds(): array;

    abstract protected function sectionLabelSingular(): string;

    abstract protected function sectionLabelPlural(): string;

    public function configureCrud(Crud $crud): Crud
    {
        return $this->applySortableCrudDefaults($crud)
            ->setEntityLabelInSingular($this->sectionLabelSingular())
            ->setEntityLabelInPlural($this->sectionLabelPlural())
            ->setPageTitle(Crud::PAGE_INDEX, $this->sectionLabelPlural())
            ->setPageTitle(Crud::PAGE_NEW, 'Новая запись')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $kinds = $this->personKinds();
        if (1 === \count($kinds)) {
            return $qb
                ->andWhere('entity.kind = :personKind')
                ->setParameter('personKind', $kinds[0]);
        }

        return $qb
            ->andWhere('entity.kind IN (:personKinds)')
            ->setParameter('personKinds', $kinds);
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', ' ')
                ->setTemplatePath('admin/field/drag.html.twig')
                ->setSortable(false);
            yield PublicImageField::thumbnail('photoPath', 'people');
            yield TextField::new('name', 'Имя');
            foreach ($this->indexExtraFields() as $field) {
                yield $field;
            }
            yield BooleanField::new('published', 'На сайте');

            return;
        }

        yield FormField::addFieldset('Основное');
        foreach ($this->formExtraFields() as $field) {
            yield $field;
        }
        yield PublicImageField::new('photoPath', 'Фото', 'people')
            ->setHelp('Портрет для карточки и модалки');
        yield TextField::new('name', 'Имя');
        yield FormField::addFieldset('Тексты');
        yield TextareaField::new('excerpt', 'Кратко')->setNumOfRows(3);
        yield HtmlEditorField::new('bio', 'Био', 8)
            ->setHelp('Только текст. Фото — в поле выше, не вставляйте картинки в HTML.');
        yield BooleanField::new('published', 'Опубликовано');
    }

    /** @return iterable<int, mixed> */
    protected function indexExtraFields(): iterable
    {
        return [];
    }

    /** @return iterable<int, mixed> */
    protected function formExtraFields(): iterable
    {
        return [];
    }
}
