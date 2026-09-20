<?php

namespace App\Admin;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\PricingPeriod;
use App\Repository\FestivalSeasonRepository;
use App\Service\Admin\AdminSeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FestivalSeasonCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly FestivalSeasonRepository $seasons,
        private readonly AdminSeasonContext $seasonContext,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FestivalSeason::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Сезон')
            ->setEntityLabelInPlural('Сезоны')
            ->setPageTitle(Crud::PAGE_INDEX, 'Сезоны')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый сезон')
            ->setPageTitle(Crud::PAGE_EDIT, 'Сезон')
            ->setDefaultSort(['year' => 'DESC'])
            ->setHelp(Crud::PAGE_INDEX, 'Текущий сезон принимает заявки с сайта. Просмотр в админке переключается вкладками вверху.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Новый сезон'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action): Action => $action->displayIf(
                fn (?FestivalSeason $season): bool => $season !== null && !$this->isSeasonUsed($season),
            ));
    }

    public function createEntity(string $entityFqcn): FestivalSeason
    {
        $year = $this->seasons->nextYear();
        $season = new FestivalSeason();
        $season->setYear($year);
        $season->setName(sprintf('Хануман Фест %d', $year));

        return $season;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof FestivalSeason) {
            $this->normalizeSeason($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof FestivalSeason && $entityInstance->isCurrent()) {
            $this->seasonContext->setSelectedSeason($entityInstance);
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof FestivalSeason) {
            $this->normalizeSeason($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof FestivalSeason && $entityInstance->isCurrent()) {
            $this->seasonContext->setSelectedSeason($entityInstance);
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof FestivalSeason) {
            parent::deleteEntity($entityManager, $entityInstance);

            return;
        }

        if ($this->isSeasonUsed($entityInstance)) {
            throw new \RuntimeException('Нельзя удалить сезон: есть заявки или периоды цен.');
        }

        $wasCurrent = $entityInstance->isCurrent();
        parent::deleteEntity($entityManager, $entityInstance);

        if ($wasCurrent) {
            $fallback = $this->seasons->findAllOrdered()[0] ?? null;
            if ($fallback) {
                $this->seasons->makeExclusiveCurrent($fallback);
                $entityManager->flush();
            }
        }
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('year', 'Год')
            ->setHelp('Год фестиваля, например 2028.')
            ->setFormTypeOption('attr', ['min' => 2020, 'max' => 2100]);
        yield TextField::new('name', 'Название');
        yield BooleanField::new('isCurrent', 'Текущий для сайта')
            ->setHelp('Новые заявки с сайта идут в этот сезон. В админке год смотрите вкладками вверху.')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);
    }

    private function normalizeSeason(FestivalSeason $season): void
    {
        if (trim($season->getName()) === '') {
            $season->setName(sprintf('Хануман Фест %d', $season->getYear()));
        }

        if ($season->isCurrent()) {
            $this->seasons->makeExclusiveCurrent($season);

            return;
        }

        $hasOtherCurrent = false;
        foreach ($this->seasons->findAll() as $other) {
            if ($other !== $season && $other->isCurrent()) {
                $hasOtherCurrent = true;
                break;
            }
        }

        if (!$hasOtherCurrent) {
            $season->setIsCurrent(true);
        }
    }

    private function isSeasonUsed(FestivalSeason $season): bool
    {
        if ($season->getId() === null) {
            return false;
        }

        $apps = $this->em->getRepository(Application::class)->count(['season' => $season]);
        $periods = $this->em->getRepository(PricingPeriod::class)->count(['season' => $season]);

        return $apps > 0 || $periods > 0;
    }
}
