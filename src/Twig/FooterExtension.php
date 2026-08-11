<?php

namespace App\Twig;

use App\Service\Content\FooterContactsParser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FooterExtension extends AbstractExtension
{
    public function __construct(private readonly FooterContactsParser $parser)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('footer_contact_links', $this->parser->contactLinks(...)),
            new TwigFunction('footer_social_links', $this->parser->socialLinks(...)),
        ];
    }
}
