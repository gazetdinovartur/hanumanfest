<?php

namespace App\Service\Content;

use App\Entity\SitePage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Default kitchen-page videos: copy into public uploads so admin FileField can show them.
 */
final class KitchenPageVideos
{
    public const SUBDIR = 'pages/kitchen';

    /** @var array<string, string> SitePage setter property => filename */
    public const FILES = [
        'kitchenVideo1' => 'IMG_8652.mp4',
        'kitchenVideo2' => 'IMG_8223.mp4',
        'kitchenVideo3' => 'IMG_8224.mp4',
        'kitchenVideo4' => 'IMG_8222.mp4',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<string> missing filenames
     */
    public function copyIntoUploads(): array
    {
        $destDir = $this->projectDir.'/public/uploads/'.self::SUBDIR;
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new \RuntimeException('Cannot create kitchen video directory: '.$destDir);
        }

        $missing = [];
        foreach (self::FILES as $file) {
            $dest = $destDir.'/'.$file;
            if (is_file($dest)) {
                continue;
            }
            $source = $this->findSource($file);
            if ($source === null) {
                $missing[] = $file;
                continue;
            }
            if (!copy($source, $dest)) {
                throw new \RuntimeException('Cannot copy kitchen video to '.$dest);
            }
        }

        return $missing;
    }

    public function applyTo(SitePage $page): void
    {
        foreach (self::FILES as $property => $file) {
            $getter = 'get'.ucfirst($property);
            $setter = 'set'.ucfirst($property);
            $current = trim((string) $page->$getter());
            $webPath = '/uploads/'.self::SUBDIR.'/'.$file;
            $onDisk = is_file($this->projectDir.'/public/uploads/'.self::SUBDIR.'/'.$file);
            if (!$onDisk) {
                continue;
            }
            if ($current === '' || str_contains($current, '/uploads/wp/')) {
                $page->$setter($webPath);
            }
        }
    }

    private function findSource(string $file): ?string
    {
        foreach ([
            $this->projectDir.'/public/uploads/wp/2026/03/'.$file,
            $this->projectDir.'/data/site-pages/videos/'.$file,
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
