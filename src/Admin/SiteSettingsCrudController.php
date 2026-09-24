<?php

namespace App\Admin;

use App\Admin\Field\HtmlEditorField;
use App\Admin\Field\PublicImageField;
use App\Entity\SiteSettings;
use App\Service\Content\SiteContentDefaults;
use App\Service\Content\UploadPathNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SiteSettingsCrudController extends AbstractUploadCrudController
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
        return SiteSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Настройки')
            ->setEntityLabelInPlural('Настройки')
            ->setPageTitle(Crud::PAGE_EDIT, 'Настройки сайта')
            ->setPageTitle(Crud::PAGE_INDEX, 'Настройки сайта');
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
        return $this->redirectToSettingsEdit();
    }

    public function new(AdminContext $context): RedirectResponse
    {
        return $this->redirectToSettingsEdit();
    }

    protected function uploadPathMap(): array
    {
        return [
            'logoPath' => 'site',
            'footerBackgroundPath' => 'site',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield FormField::addFieldset('Бренд');
        yield TextField::new('siteName', 'Название');
        yield PublicImageField::new('logoPath', 'Логотип', 'site')
            ->setHelp('Круглый логотип в шапке сайта.');
        yield PublicImageField::new('footerBackgroundPath', 'Фон футера', 'site');
        yield FormField::addFieldset('Блоки на главной');
        yield HtmlEditorField::new('discountsHtml', 'Скидки', 6);
        yield HtmlEditorField::new('tentNoteHtml', 'Примечание о палатках', 4);
        yield HtmlEditorField::new('cooperationCtaHtml', 'Призыв к сотрудничеству', 4);
        yield FormField::addFieldset('Контакты');
        yield TextareaField::new('companyInfo', 'Реквизиты')
            ->setNumOfRows(4);
        yield TextField::new('phone', 'Телефон');
        yield TextField::new('phone2', 'Телефон 2');
        yield EmailField::new('email', 'Email');
        yield UrlField::new('vkUrl', 'ВКонтакте');
        yield UrlField::new('telegramUrl', 'Telegram');
        yield UrlField::new('facebookUrl', 'Facebook');
        yield UrlField::new('instagramUrl', 'Instagram');
    }

    private function redirectToSettingsEdit(): RedirectResponse
    {
        $this->siteContentDefaults->ensureCoreContent();

        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']);
        if (!$settings instanceof SiteSettings || null === $settings->getId()) {
            throw new \RuntimeException('Site settings record missing after CMS bootstrap.');
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($settings->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
