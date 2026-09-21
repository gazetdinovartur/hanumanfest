<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Enum\ReviewMediaKind;
use App\Repository\ReviewMediaRepository;
use App\Service\Content\ImageOptimizer;
use App\Service\Content\ImageVariantResolver;
use App\Service\Content\ReviewMediaUploadService;
use App\Service\Content\UploadFileRemover;
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
final class ReviewMediaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReviewMediaRepository $mediaRepository,
        private readonly ReviewMediaUploadService $uploadService,
        private readonly UploadFileRemover $uploadFileRemover,
        private readonly ImageVariantResolver $variantResolver,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[AdminRoute(path: '/reviews/{id}/media/upload', name: 'review_media_upload', options: ['methods' => ['POST'], 'requirements' => ['id' => '\d+']])]
    public function upload(int $id, Request $request): JsonResponse
    {
        $review = $this->em->find(Review::class, $id);
        if (!$review instanceof Review) {
            return $this->json(['ok' => false, 'error' => 'Отзыв не найден'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('review_media_admin', (string) $request->headers->get('X-Review-Media-Token'))) {
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
            $created = $this->uploadService->uploadMany($review, array_values(array_filter($raw)));

            return $this->json([
                'ok' => true,
                'count' => count($created),
                'items' => array_map([$this, 'serializeItem'], $created),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[AdminRoute(path: '/reviews/{id}/media/reorder', name: 'review_media_reorder', options: ['methods' => ['POST'], 'requirements' => ['id' => '\d+']])]
    public function reorder(int $id, Request $request): JsonResponse
    {
        $review = $this->em->find(Review::class, $id);
        if (!$review instanceof Review) {
            return $this->json(['ok' => false, 'error' => 'Отзыв не найден'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->csrf->isTokenValid(new CsrfToken('review_media_admin', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        $order = 1;
        foreach ($payload['ids'] ?? [] as $mediaId) {
            $item = $this->mediaRepository->find((int) $mediaId);
            if (!$item instanceof ReviewMedia || $item->getReview()?->getId() !== $review->getId()) {
                continue;
            }
            $item->setSortOrder($order);
            ++$order;
        }
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[AdminRoute(path: '/reviews/media/{id}', name: 'review_media_item', options: ['methods' => ['PATCH'], 'requirements' => ['id' => '\d+']])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->mediaRepository->find($id);
        if (!$item instanceof ReviewMedia) {
            return $this->json(['ok' => false, 'error' => 'Не найдено'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->csrf->isTokenValid(new CsrfToken('review_media_admin', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        if (array_key_exists('published', $payload)) {
            $item->setPublished((bool) $payload['published']);
        }
        $this->em->flush();

        return $this->json(['ok' => true, 'item' => $this->serializeItem($item)]);
    }

    #[AdminRoute(path: '/reviews/media/{id}', name: 'review_media_delete', options: ['methods' => ['DELETE'], 'requirements' => ['id' => '\d+']])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $item = $this->mediaRepository->find($id);
        if (!$item instanceof ReviewMedia) {
            return $this->json(['ok' => false, 'error' => 'Не найдено'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('review_media_admin', (string) $request->headers->get('X-Review-Media-Token'))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        $path = $item->getPath();
        $this->em->remove($item);
        $this->em->flush();
        $this->uploadFileRemover->deletePublicPath($path);

        return $this->json(['ok' => true]);
    }

    /** @return array{id: int|null, kind: string, path: string, url: string, thumbUrl: string, published: bool, sortOrder: int} */
    private function serializeItem(ReviewMedia $item): array
    {
        $path = $item->getPath();
        $url = $path;
        $thumb = $item->getKind() === ReviewMediaKind::Image
            ? ($this->variantResolver->resolve($path, ImageOptimizer::VARIANT_THUMB) ?? $path)
            : $path;

        return [
            'id' => $item->getId(),
            'kind' => $item->getKind()->value,
            'path' => $path,
            'url' => $url,
            'thumbUrl' => $thumb,
            'published' => $item->isPublished(),
            'sortOrder' => $item->getSortOrder(),
        ];
    }
}
