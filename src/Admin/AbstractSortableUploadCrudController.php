<?php

namespace App\Admin;

use App\Service\Content\UploadPathNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

abstract class AbstractSortableUploadCrudController extends AbstractUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($uploadPathNormalizer);
    }

    abstract protected function sortableKind(): string;

    protected function applySortableCrudDefaults(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody(sprintf(
                '<meta name="hf-reorder-csrf" content="%s"><meta name="hf-reorder-url" content="%s">',
                htmlspecialchars($this->csrfTokenManager->getToken('cms_reorder')->getValue(), ENT_QUOTES),
                htmlspecialchars($this->generateUrl('admin_cms_reorder', ['kind' => $this->sortableKind()]), ENT_QUOTES),
            ))
            ->addJsFile('https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js')
            ->addJsFile('js/admin-sortable.js');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::BATCH_DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, Action::DELETE])
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                static fn (Action $action): Action => $action
                    ->setLabel(false)
                    ->setIcon('fa fa-pen')
                    ->setHtmlAttributes(['title' => 'Редактировать', 'aria-label' => 'Редактировать']),
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                static fn (Action $action): Action => $action
                    ->setLabel(false)
                    ->setIcon('fa fa-trash-alt')
                    ->setHtmlAttributes(['title' => 'Удалить', 'aria-label' => 'Удалить']),
            );
    }
}
