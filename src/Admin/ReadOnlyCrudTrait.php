<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

trait ReadOnlyCrudTrait
{
    protected function configureReadOnlyActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                static fn (Action $action): Action => $action
                    ->setLabel(false)
                    ->addCssClass('d-none')
                    ->setHtmlAttributes(['title' => 'Открыть', 'aria-label' => 'Открыть']),
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
