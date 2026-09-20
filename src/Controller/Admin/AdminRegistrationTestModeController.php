<?php

namespace App\Controller\Admin;

use App\Service\RegistrationTestMode;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminRegistrationTestModeController extends AbstractController
{
    public function __construct(
        private readonly RegistrationTestMode $registrationTestMode,
    ) {
    }

    #[AdminRoute(path: '/registration-test-mode', name: 'registration_test_mode', options: ['methods' => ['POST']])]
    public function toggle(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_registration_test_mode', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $enabled = $request->request->getBoolean('enabled');
        $this->registrationTestMode->setEnabled($enabled);

        return $this->redirectToRoute('admin');
    }
}
