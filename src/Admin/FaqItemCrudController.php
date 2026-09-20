<?php

namespace App\Admin;

use App\Admin\Field\HtmlEditorField;
use App\Entity\FaqItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class FaqItemCrudController extends AbstractSortableCrudController
{
    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        parent::__construct($csrfTokenManager);
    }

    public static function getEntityFqcn(): string
    {
        return FaqItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->applySortableCrudDefaults($crud)
            ->setEntityLabelInSingular('Вопрос')
            ->setEntityLabelInPlural('FAQ')
            ->setPageTitle(Crud::PAGE_INDEX, 'FAQ')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый вопрос')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать вопрос');
    }

    protected function sortableKind(): string
    {
        return 'faq';
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', ' ')
                ->setTemplatePath('admin/field/drag.html.twig')
                ->setSortable(false);
            yield TextField::new('question', 'Вопрос');
            yield BooleanField::new('published', 'На сайте');

            return;
        }

        yield FormField::addFieldset('FAQ');
        yield TextField::new('question', 'Вопрос');
        yield HtmlEditorField::new('answer', 'Ответ', 6);
        yield BooleanField::new('published', 'Опубликовано');
    }
}
