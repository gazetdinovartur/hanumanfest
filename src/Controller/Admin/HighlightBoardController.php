<?php

namespace App\Controller\Admin;

use App\Admin\HomeHighlightCrudController;
use App\Entity\HomeHighlight;
use App\Enum\HomeHighlightColumn;
use App\Repository\HomeHighlightRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class HighlightBoardController extends AbstractController
{
    public function __construct(
        private readonly HomeHighlightRepository $highlights,
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[AdminRoute(path: '/highlights', name: 'highlights', options: ['methods' => ['GET']])]
    public function index(): Response
    {
        return $this->render('admin/highlights.html.twig', [
            'left' => array_map($this->tileView(...), $this->highlights->findOrderedByColumn(HomeHighlightColumn::Left)),
            'right' => array_map($this->tileView(...), $this->highlights->findOrderedByColumn(HomeHighlightColumn::Right)),
            'newLeftUrl' => $this->crudUrl(Action::NEW, extra: ['column' => HomeHighlightColumn::Left->value]),
            'newRightUrl' => $this->crudUrl(Action::NEW, extra: ['column' => HomeHighlightColumn::Right->value]),
            'highlightsCsrf' => $this->csrf->getToken('highlights_admin')->getValue(),
        ]);
    }

    #[AdminRoute(path: '/highlights/reorder', name: 'highlights_reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->csrf->isTokenValid(new CsrfToken('highlights_admin', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        if (!array_key_exists('left', $payload) || !array_key_exists('right', $payload)) {
            return $this->json(['ok' => false, 'error' => 'Нужны списки left и right'], Response::HTTP_BAD_REQUEST);
        }

        $this->applyColumnOrder(HomeHighlightColumn::Left, $payload['left'] ?? []);
        $this->applyColumnOrder(HomeHighlightColumn::Right, $payload['right'] ?? []);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    /**
     * @param list<mixed> $ids
     */
    private function applyColumnOrder(HomeHighlightColumn $column, mixed $ids): void
    {
        if (!\is_array($ids)) {
            return;
        }

        $order = 1;
        foreach ($ids as $id) {
            $tile = $this->highlights->find((int) $id);
            if (!$tile instanceof HomeHighlight) {
                continue;
            }
            $tile->setColumnSide($column);
            $tile->setSortOrder($order);
            ++$order;
        }
    }

    /**
     * @return array{id: int|null, text: string, style: string, published: bool, editUrl: string}
     */
    private function tileView(HomeHighlight $tile): array
    {
        return [
            'id' => $tile->getId(),
            'text' => $tile->getText(),
            'style' => $tile->getStyle()->value,
            'published' => $tile->isPublished(),
            'editUrl' => $this->crudUrl(Action::EDIT, $tile->getId()),
        ];
    }

    /** @param array<string, string> $extra */
    private function crudUrl(string $action, ?int $id = null, array $extra = []): string
    {
        $url = $this->adminUrlGenerator
            ->unsetAll()
            ->setController(HomeHighlightCrudController::class)
            ->setAction($action);

        if (null !== $id) {
            $url->setEntityId($id);
        }
        foreach ($extra as $key => $value) {
            $url->set($key, $value);
        }

        return $url->generateUrl();
    }
}
