<?php

namespace App\Tests\Integration\EventListener;

use App\Entity\GalleryItem;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class UploadFileCleanupListenerTest extends DatabaseTestCase
{
    public function testDeletingGalleryItemRemovesFileFromDisk(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $uploadsDir = $projectDir.'/public/uploads';
        $galleryDir = $uploadsDir.'/gallery';
        if (!is_dir($galleryDir)) {
            mkdir($galleryDir, 0775, true);
        }

        $filename = 'test-'.bin2hex(random_bytes(4)).'.jpg';
        $absolute = $galleryDir.'/'.$filename;
        file_put_contents($absolute, 'test');

        $item = new GalleryItem();
        $item->setImagePath('/uploads/gallery/'.$filename);
        $item->setSortOrder(1);
        $item->setPublished(true);

        $em = $this->entityManager;
        $em->persist($item);
        $em->flush();

        self::assertFileExists($absolute);

        $em->remove($item);
        $em->flush();

        self::assertFileDoesNotExist($absolute);
    }
}
