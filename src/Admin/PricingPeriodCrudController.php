<?php

namespace App\Admin;

use App\Entity\PricingPeriod;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** CRUD kept for EasyAdmin entity registration; UI lives in pricing matrix. */
class PricingPeriodCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PricingPeriod::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Период стоимости')
            ->setEntityLabelInPlural('Периоды стоимости');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT, Action::DETAIL, Action::DELETE, Action::BATCH_DELETE);
    }

    public function index(AdminContext $context): RedirectResponse
    {
        return $this->redirectToRoute('admin_pricing_matrix');
    }

    public function new(AdminContext $context): RedirectResponse
    {
        return $this->redirectToRoute('admin_pricing_matrix');
    }

    public function detail(AdminContext $context): RedirectResponse
    {
        return $this->redirectToRoute('admin_pricing_matrix');
    }

    public function edit(AdminContext $context): RedirectResponse
    {
        return $this->redirectToRoute('admin_pricing_matrix');
    }
}
