<?php

namespace App\Admin;

use App\Infrastructure\GoogleSheets\GoogleSheetsRegistrationsReference;
use App\Infrastructure\GoogleSheets\GoogleSpreadsheetUrl;
use App\Service\Admin\AdminDashboardStatsService;
use App\Service\Admin\AdminSeasonContext;
use App\Service\Content\SiteContentDefaults;
use App\Service\RegistrationTestMode;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminDashboardStatsService $dashboardStats,
        private readonly AdminSeasonContext $seasonContext,
        private readonly GoogleSheetsRegistrationsReference $registrations,
        #[Autowire(service: 'App\\Infrastructure\\GoogleSheets\\GoogleSheetsTestRegistrationsReference')]
        private readonly GoogleSheetsRegistrationsReference $testRegistrations,
        private readonly SiteContentDefaults $siteContentDefaults,
        private readonly RegistrationTestMode $registrationTestMode,
        private readonly string $scheduleSheetUrl = '',
    ) {
    }

    public function index(): Response
    {
        $this->siteContentDefaults->ensureCoreContent();
        $season = $this->seasonContext->getSelectedSeason();
        $stats = $this->dashboardStats->getRegistrationStats($season);

        $testSheetUrl = $this->testRegistrations->isConfigured()
            ? $this->testRegistrations->spreadsheetViewUrl()
            : '';

        return $this->render('admin/dashboard.html.twig', [
            'season' => $season,
            'registrationsTotal' => $stats['registrationsTotal'],
            'paidApplications' => $stats['paidApplications'],
            'refundsCount' => $stats['refundsCount'],
            'registrationsSpreadsheetUrl' => $this->registrations->spreadsheetViewUrl(),
            'testRegistrationsSpreadsheetUrl' => $testSheetUrl !== '' ? $testSheetUrl : null,
            'scheduleSpreadsheetUrl' => GoogleSpreadsheetUrl::editUrlFrom($this->scheduleSheetUrl),
            'registrationTestMode' => $this->registrationTestMode->isEnabled(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/uploads/wp/2025/10/logo-hanuman.png" alt="" class="hf-admin-brand-logo" width="36" height="36"> Хануман Фест')
            ->setFaviconPath('/uploads/wp/2025/10/logo-hanuman.png')
            ->disableDarkMode();
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->addFormTheme('admin/form/form_theme.html.twig');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/admin-custom.css')
            ->addJsFile('js/admin-html-editor.js')
            ->addJsFile('js/admin-site-page.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Навигация', 'fa fa-compass');

        yield MenuItem::section('Сайт');
        yield MenuItem::linkTo(HomeHeroCrudController::class, 'Главный экран', 'fa fa-image');
        yield MenuItem::linkToRoute('Плитки «О фестивале»', 'fa fa-th', 'admin_highlights');
        yield MenuItem::linkTo(SiteSettingsCrudController::class, 'Настройки', 'fa fa-sliders');
        yield MenuItem::linkTo(GuestPersonCrudController::class, 'Специальные гости', 'fa fa-star');
        yield MenuItem::linkTo(MusicianPersonCrudController::class, 'Музыканты', 'fa fa-music');
        yield MenuItem::linkTo(MasterPersonCrudController::class, 'Мастера и практики', 'fa fa-hands');
        yield MenuItem::linkToRoute('Галерея «Как это было»', 'fa fa-images', 'admin_gallery');
        yield MenuItem::linkTo(FaqItemCrudController::class, 'FAQ', 'fa fa-circle-question');
        yield MenuItem::linkTo(SitePageCrudController::class, 'Страницы', 'fa fa-file-lines');
        yield MenuItem::linkTo(InfoBlockCrudController::class, 'Инфоблоки', 'fa fa-info');
        yield MenuItem::linkTo(ReviewCrudController::class, 'Отзывы', 'fa fa-comment');
        yield MenuItem::linkToUrl('Открыть сайт', 'fa fa-external-link', '/');

        yield MenuItem::section('Регистрация');
        yield MenuItem::linkTo(ApplicationCrudController::class, 'Заявки', 'fa fa-file-alt');
        yield MenuItem::linkTo(PaymentCrudController::class, 'Платежи', 'fa fa-credit-card');
        yield MenuItem::linkTo(UserCrudController::class, 'Пользователи', 'fa fa-user');

        yield MenuItem::section('Цены');
        yield MenuItem::linkToRoute('Периоды и цены', 'fa fa-table', 'admin_pricing_matrix');
    }
}
