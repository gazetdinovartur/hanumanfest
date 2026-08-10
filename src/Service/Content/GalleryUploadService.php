<?php

namespace App\Service\Content;

use App\Entity\GalleryItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GalleryUploadService
{
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_BYTES = 12 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<GalleryItem>
     */
    public function uploadMany(array $files): array
    {
        $created = [];
        $maxOrder = (int) $this->em->createQueryBuilder()
            ->select('MAX(g.sortOrder)')
            ->from(GalleryItem::class, 'g')
            ->getQuery()
            ->getSingleScalarResult();

        $dir = $this->projectDir.'/public/uploads/gallery';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create gallery upload directory.');
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $mime = $file->getMimeType() ?? '';
            if (!in_array($mime, self::ALLOWED, true)) {
                throw new \InvalidArgumentException(sprintf('Неподдерживаемый тип: %s', $mime));
            }
            if ($file->getSize() > self::MAX_BYTES) {
                throw new \InvalidArgumentException('Файл больше 12 МБ.');
            }

            $ext = $file->guessExtension() ?: 'jpg';
            $name = bin2hex(random_bytes(8)).'.'.$ext;
            $file->move($dir, $name);

            $item = new GalleryItem();
            $item->setImagePath('/uploads/gallery/'.$name);
            $item->setSortOrder(++$maxOrder);
            $item->setPublished(true);
            $this->em->persist($item);
            $created[] = $item;
        }

        $this->em->flush();

        return $created;
    }
}
