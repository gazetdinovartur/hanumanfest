<?php

namespace App\Service\Content;

use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Enum\ReviewMediaKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ReviewMediaUploadService
{
    private const IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const VIDEO_MIME = ['video/mp4', 'video/webm', 'video/quicktime'];
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;
    private const MAX_VIDEO_BYTES = 80 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<ReviewMedia>
     */
    public function uploadMany(Review $review, array $files): array
    {
        $created = [];
        $maxOrder = 0;
        foreach ($review->getMedia() as $existing) {
            $maxOrder = max($maxOrder, $existing->getSortOrder());
        }

        $imageDir = $this->projectDir.'/public/uploads/reviews/media';
        $videoDir = $this->projectDir.'/public/uploads/reviews/video';
        foreach ([$imageDir, $videoDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create review media directory.');
            }
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            if (!$file->isValid()) {
                $this->throwIfUploadRejected($file);
            }
            $mime = $file->getMimeType() ?? '';
            $isImage = in_array($mime, self::IMAGE_MIME, true);
            $isVideo = in_array($mime, self::VIDEO_MIME, true);
            if (!$isImage && !$isVideo) {
                throw new \InvalidArgumentException(sprintf('Неподдерживаемый тип: %s', $mime));
            }
            if ($isImage && $file->getSize() > self::MAX_IMAGE_BYTES) {
                throw new \InvalidArgumentException('Фото больше 12 МБ.');
            }
            if ($isVideo && $file->getSize() > self::MAX_VIDEO_BYTES) {
                throw new \InvalidArgumentException('Видео больше 80 МБ.');
            }

            $dir = $isImage ? $imageDir : $videoDir;
            $ext = $file->guessExtension() ?: ($isImage ? 'jpg' : 'mp4');
            $name = bin2hex(random_bytes(8)).'.'.$ext;
            $file->move($dir, $name);

            $publicPath = $isImage
                ? '/uploads/reviews/media/'.$name
                : '/uploads/reviews/video/'.$name;

            $item = new ReviewMedia();
            $item->setKind($isImage ? ReviewMediaKind::Image : ReviewMediaKind::Video);
            $item->setPath($publicPath);
            $item->setSortOrder(++$maxOrder);
            $item->setPublished(true);
            $review->addMedia($item);
            $this->em->persist($item);
            $created[] = $item;
        }

        $this->em->flush();

        return $created;
    }

    private function throwIfUploadRejected(UploadedFile $file): never
    {
        $error = $file->getError();
        if (\UPLOAD_ERR_INI_SIZE === $error || \UPLOAD_ERR_FORM_SIZE === $error) {
            throw new \InvalidArgumentException('Файл слишком большой.');
        }

        throw new \InvalidArgumentException('Не удалось принять файл.');
    }
}
