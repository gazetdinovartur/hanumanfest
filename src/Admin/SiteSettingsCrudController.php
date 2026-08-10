<?php

namespace App\Admin;

use App\Admin\Field\PublicImageField;
use App\Entity\SiteSettings;
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
        yield TextField::new('tagline', 'Слоган');
        yield PublicImageField::new('logoPath', 'Логотип', 'site');
        yield PublicImageField::new('footerBackgroundPath', 'Фон футера', 'site');
        yield FormField::addFieldset('Контакты');
        yield TextareaField::new('companyInfo', 'Реквизиты')
            ->setNumOfRows(4);
        yield TextareaField::new('contactsHtml', 'Контакты')
            ->setHelp('По одной строке: телефон, email, ссылки.')
            ->setNumOfRows(5);
        yield UrlField::new('vkUrl', 'VK');
        yield UrlField::new('telegramUrl', 'Telegram');
        yield EmailField::new('notificationEmail', 'Email уведомлений');
    }

    private function redirectToSettingsEdit(): RedirectResponse
    {
        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']) ?? new SiteSettings();
        if (null === $settings->getId()) {
            $this->em->persist($settings);
            $this->em->flush();
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($settings->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
