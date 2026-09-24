<?php

namespace App\Admin;

use App\Entity\HomeHighlight;
use App\Enum\HomeHighlightColumn;
use App\Enum\HomeHighlightStyle;
use App\Repository\HomeHighlightRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeHighlightCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly HomeHighlightRepository $highlights,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return HomeHighlight::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Плитка')
            ->setEntityLabelInPlural('Плитки «О фестивале»')
            ->setPageTitle(Crud::PAGE_INDEX, 'Плитки «О фестивале»')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая плитка')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать плитку');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL, Action::BATCH_DELETE)
            ->update(
                Crud::PAGE_EDIT,
                Action::SAVE_AND_RETURN,
                static fn (Action $action): Action => $action->setLabel('Сохранить'),
            )
            ->update(
                Crud::PAGE_NEW,
                Action::SAVE_AND_RETURN,
                static fn (Action $action): Action => $action->setLabel('Сохранить'),
            );
    }

    public function index(AdminContext $context): RedirectResponse
    {
        return $this->redirectToRoute('admin_highlights');
    }

    public function createEntity(string $entityFqcn): object
    {
        $tile = new HomeHighlight();
        $column = HomeHighlightColumn::tryFrom((string) $this->getContext()?->getRequest()->query->get('column'));
        if (HomeHighlightColumn::Left === $column) {
            $tile->setColumnSide(HomeHighlightColumn::Left);
            $tile->setStyle(HomeHighlightStyle::Big);
        } elseif (HomeHighlightColumn::Right === $column) {
            $tile->setColumnSide(HomeHighlightColumn::Right);
            $tile->setStyle(HomeHighlightStyle::Normal);
        }

        return $tile;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof HomeHighlight && 0 === $entityInstance->getSortOrder()) {
            $entityInstance->setSortOrder($this->highlights->nextSortOrder($entityInstance->getColumnSide()));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'] ?? null;
        if (Action::SAVE_AND_CONTINUE === $submitButtonName) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        return $this->redirectToRoute('admin_highlights');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Плитка');
        yield TextareaField::new('text', 'Текст')->setNumOfRows(3)->setRequired(true);
        yield ChoiceField::new('columnSide', 'Колонка')
            ->setChoices([
                'Слева' => HomeHighlightColumn::Left,
                'Справа' => HomeHighlightColumn::Right,
            ])
            ->renderAsNativeWidget()
            ->setHelp('Порядок внутри колонки задаётся перетаскиванием на схеме.');
        yield ChoiceField::new('style', 'Вид')
            ->setChoices([
                'Крупный шрифт' => HomeHighlightStyle::Big,
                'Обычная' => HomeHighlightStyle::Normal,
                'На всю ширину колонки' => HomeHighlightStyle::Wide,
            ])
            ->renderAsNativeWidget()
            ->setHelp('Слева обычно крупный шрифт. Справа обычные плитки, нижняя часто на всю ширину.');
        yield BooleanField::new('published', 'Показывать на сайте');
    }
}
