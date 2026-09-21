<?php

namespace App\Tests\Unit\Service\Content;

use App\Entity\SitePage;
use App\Service\Content\KitchenPageVideos;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class KitchenPageVideosTest extends TestCase
{
    public function testCopiesFromWpUploadsAndAppliesPathsWhenEmpty(): void
    {
        $root = sys_get_temp_dir().'/hf-kitchen-'.bin2hex(random_bytes(4));
        $wpDir = $root.'/public/uploads/wp/2026/03';
        self::assertTrue(mkdir($wpDir, 0775, true));
        foreach (KitchenPageVideos::FILES as $file) {
            self::assertNotFalse(file_put_contents($wpDir.'/'.$file, 'mp4'));
        }

        $service = new KitchenPageVideos($root);
        self::assertSame([], $service->copyIntoUploads());
        foreach (KitchenPageVideos::FILES as $file) {
            self::assertFileExists($root.'/public/uploads/pages/kitchen/'.$file);
        }

        $page = new SitePage();
        $service->applyTo($page);
        self::assertSame('/uploads/pages/kitchen/IMG_8652.mp4', $page->getKitchenVideo1());
        self::assertSame('/uploads/pages/kitchen/IMG_8222.mp4', $page->getKitchenVideo4());
    }

    public function testDoesNotOverwriteCustomAdminUpload(): void
    {
        $root = sys_get_temp_dir().'/hf-kitchen-'.bin2hex(random_bytes(4));
        $destDir = $root.'/public/uploads/pages/kitchen';
        self::assertTrue(mkdir($destDir, 0775, true));
        self::assertNotFalse(file_put_contents($destDir.'/IMG_8652.mp4', 'mp4'));

        $page = (new SitePage())->setKitchenVideo1('/uploads/pages/kitchen/custom-uuid.mp4');
        (new KitchenPageVideos($root))->applyTo($page);

        self::assertSame('/uploads/pages/kitchen/custom-uuid.mp4', $page->getKitchenVideo1());
    }

    public function testReportsMissingFiles(): void
    {
        $root = sys_get_temp_dir().'/hf-kitchen-'.bin2hex(random_bytes(4));
        self::assertTrue(mkdir($root.'/public', 0775, true));

        $missing = (new KitchenPageVideos($root))->copyIntoUploads();

        self::assertSame(array_values(KitchenPageVideos::FILES), $missing);
    }
}
