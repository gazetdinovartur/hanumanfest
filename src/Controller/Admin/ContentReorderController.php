<?php

namespace App\Controller\Admin;

use App\Entity\FaqItem;
use App\Entity\HomeHighlight;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Enum\PersonKind;
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
final class ContentReorderController extends AbstractController
{
    /** @var array<string, class-string> */
    private const KIND_ENTITY = [
        'person-guest' => Person::class,
        'person-musician' => Person::class,
        'person-master' => Person::class,
        'faq' => FaqItem::class,
        'info' => InfoBlock::class,
        'review' => Review::class,
        'highlight' => HomeHighlight::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[AdminRoute(path: '/cms/reorder/{kind}', name: 'cms_reorder', options: ['methods' => ['POST'], 'requirements' => ['kind' => 'person-guest|person-musician|person-master|faq|info|review|highlight']])]
    public function reorder(string $kind, Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->csrf->isTokenValid(new CsrfToken('cms_reorder', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        $entityClass = self::KIND_ENTITY[$kind] ?? null;
        if (null === $entityClass) {
            return $this->json(['ok' => false, 'error' => 'Неизвестный тип'], Response::HTTP_BAD_REQUEST);
        }

        /** @var list<int|string> $ids */
        $ids = $payload['ids'] ?? [];
        if ([] === $ids) {
            return $this->json(['ok' => false, 'error' => 'Пустой список'], Response::HTTP_BAD_REQUEST);
        }

        $order = 1;
        if (str_starts_with($kind, 'person-')) {
            $expectedKind = match ($kind) {
                'person-guest' => PersonKind::Guest,
                'person-musician' => PersonKind::Musician,
                'person-master' => PersonKind::Master,
                default => null,
            };
            if (null === $expectedKind) {
                return $this->json(['ok' => false, 'error' => 'Неизвестный тип'], Response::HTTP_BAD_REQUEST);
            }
            foreach ($ids as $id) {
                $entity = $this->em->find(Person::class, (int) $id);
                if (!$entity instanceof Person || $entity->getKind() !== $expectedKind) {
                    continue;
                }
                $entity->setSortOrder($order++);
            }
        } else {
            foreach ($ids as $id) {
                $entity = $this->em->find($entityClass, (int) $id);
                if (!is_object($entity) || !method_exists($entity, 'setSortOrder')) {
                    continue;
                }
                $entity->setSortOrder($order++);
            }
        }

        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
