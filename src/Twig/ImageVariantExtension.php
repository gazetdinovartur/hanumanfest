<?php

namespace App\Twig;

use App\Service\Content\ImageOptimizer;
use App\Service\Content\ImageVariantResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ImageVariantExtension extends AbstractExtension
{
    public function __construct(
        private readonly ImageVariantResolver $resolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_variant', $this->variant(...)),
        ];
    }

    public function variant(?string $path, string $kind = ImageOptimizer::VARIANT_DISPLAY): ?string
    {
        return $this->resolver->resolve($path, $kind);
    }
}
