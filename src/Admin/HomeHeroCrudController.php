<?php

namespace App\Admin;

use App\Admin\Field\HtmlEditorField;
use App\Admin\Field\PublicImageField;
use App\Admin\Field\PublicVideoField;
use App\Entity\HomeHero;
use App\Service\Content\SiteContentDefaults;
use App\Service\Content\UploadPathNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeHeroCrudController extends AbstractUploadCrudController
{
    public function __construct(
        UploadPathNormalizer $uploadPathNormalizer,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly SiteContentDefaults $siteContentDefaults,
    ) {
        parent::__construct($uploadPathNormalizer);
    }

    public static function getEntityFqcn(): string
    {
        return HomeHero::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Главный экран')
            ->setEntityLabelInPlural('Главный экран')
            ->setPageTitle(Crud::PAGE_EDIT, 'Главный экран')
            ->setPageTitle(Crud::PAGE_INDEX, 'Главный экран');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL)
            ->update(
                Crud::PAGE_EDIT,
                Action::SAVE_AND_RETURN,
                static fn (Action $action): Action => $action->setLabel('Сохранить'),
            )
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function index(AdminContext $context): RedirectResponse
    {
        return $this->redirectToHeroEdit();
    }

    public function new(AdminContext $context): RedirectResponse
    {
        return $this->redirectToHeroEdit();
    }

    protected function uploadPathMap(): array
    {
        return [
            'imagePath' => 'hero',
            'promoVideoLeft' => 'hero/video',
            'promoVideoRight' => 'hero/video',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield FormField::addFieldset('Контент');
        yield TextField::new('eventDates', 'Даты');
        yield TextField::new('titleMain', 'Подзаголовок сверху');
        yield TextField::new('headline', 'Заголовок');
        yield TextField::new('titleSecondary', 'Подзаголовок снизу');
        yield PublicImageField::new('imagePath', 'Картинка фона', 'hero')
            ->setHelp('Фоновое изображение первого экрана (как на хануманфест.рф).');
        yield HtmlEditorField::new('aboutHtml', 'Блок «О фестивале»', 8);
        yield FormField::addFieldset('Промо-видео');
        yield PublicVideoField::new('promoVideoLeft', 'Видео слева', 'hero/video');
        yield PublicVideoField::new('promoVideoRight', 'Видео справа', 'hero/video');
        yield FormField::addFieldset('Кнопка');
        yield TextField::new('ctaLabel', 'Текст кнопки');
        yield TextField::new('ctaUrl', 'Ссылка кнопки');
    }

    private function redirectToHeroEdit(): RedirectResponse
    {
        $this->siteContentDefaults->ensureCoreContent();

        $hero = $this->em->getRepository(HomeHero::class)->findOneBy([], ['id' => 'ASC']);
        if (!$hero instanceof HomeHero || null === $hero->getId()) {
            throw new \RuntimeException('Home hero record missing after CMS bootstrap.');
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($hero->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
