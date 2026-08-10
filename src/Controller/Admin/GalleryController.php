<?php

namespace App\Controller\Admin;

use App\Entity\GalleryItem;
use App\Repository\GalleryItemRepository;
use App\Service\Content\GalleryUploadService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class GalleryController extends AbstractController
{
    public function __construct(
        private readonly GalleryItemRepository $galleryItems,
        private readonly GalleryUploadService $galleryUploadService,
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[AdminRoute(path: '/gallery', name: 'gallery', options: ['methods' => ['GET']])]
    public function index(): Response
    {
        $items = $this->galleryItems->findAllOrdered();
        $publishedCount = 0;
        foreach ($items as $item) {
            if ($item->isPublished()) {
                ++$publishedCount;
            }
        }

        return $this->render('admin/gallery.html.twig', [
            'items' => $items,
            'publishedCount' => $publishedCount,
            'hiddenCount' => count($items) - $publishedCount,
            'galleryCsrf' => $this->csrf->getToken('gallery_admin')->getValue(),
        ]);
    }

    #[AdminRoute(path: '/gallery/upload', name: 'gallery_upload', options: ['methods' => ['POST']])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('gallery_admin', (string) $request->headers->get('X-Gallery-Token'))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        try {
            $raw = $request->files->get('files', []);
            if (!is_array($raw)) {
                $raw = $raw ? [$raw] : [];
            }
            if ($raw === [] && $request->files->get('file')) {
                $raw = [$request->files->get('file')];
            }
            $created = $this->galleryUploadService->uploadMany(array_values(array_filter($raw)));

            return $this->json([
                'ok' => true,
                'count' => count($created),
                'items' => array_map([$this, 'serializeItem'], $created),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[AdminRoute(path: '/gallery/reorder', name: 'gallery_reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->csrf->isTokenValid(new CsrfToken('gallery_admin', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        /** @var list<int|string> $ids */
        $ids = $payload['ids'] ?? [];
        if ([] === $ids) {
            return $this->json(['ok' => false, 'error' => 'Пустой список'], Response::HTTP_BAD_REQUEST);
        }

        $order = 1;
        foreach ($ids as $id) {
            $item = $this->galleryItems->find((int) $id);
            if (!$item instanceof GalleryItem) {
                continue;
            }
            $item->setSortOrder($order);
            ++$order;
        }

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[AdminRoute(path: '/gallery/{id}', name: 'gallery_item', options: ['methods' => ['PATCH'], 'requirements' => ['id' => '\d+']])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->galleryItems->find($id);
        if (!$item instanceof GalleryItem) {
            return $this->json(['ok' => false, 'error' => 'Не найдено'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->csrf->isTokenValid(new CsrfToken('gallery_admin', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        if (array_key_exists('caption', $payload)) {
            $caption = \is_string($payload['caption']) ? trim($payload['caption']) : '';
            $item->setCaption('' === $caption ? null : $caption);
        }

        if (array_key_exists('published', $payload)) {
            $item->setPublished((bool) $payload['published']);
        }

        $this->em->flush();

        return $this->json(['ok' => true, 'item' => $this->serializeItem($item)]);
    }

    #[AdminRoute(path: '/gallery/{id}', name: 'gallery_delete', options: ['methods' => ['DELETE'], 'requirements' => ['id' => '\d+']])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $item = $this->galleryItems->find($id);
        if (!$item instanceof GalleryItem) {
            return $this->json(['ok' => false, 'error' => 'Не найдено'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('gallery_admin', (string) $request->headers->get('X-Gallery-Token'))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    /** @return array{id: int|null, imagePath: string, imageUrl: string, caption: ?string, published: bool, sortOrder: int} */
    private function serializeItem(GalleryItem $item): array
    {
        return [
            'id' => $item->getId(),
            'imagePath' => $item->getImagePath(),
            'imageUrl' => $item->getImagePath(),
            'caption' => $item->getCaption(),
            'published' => $item->isPublished(),
            'sortOrder' => $item->getSortOrder(),
        ];
    }
}
