<?php

namespace App\Twig;

use App\Service\Content\PublicUploadPath;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminUploadExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('public_upload_url', $this->publicUploadUrl(...)),
        ];
    }

    public function publicUploadUrl(?string $stored): ?string
    {
        return PublicUploadPath::webPath($stored);
    }
}
