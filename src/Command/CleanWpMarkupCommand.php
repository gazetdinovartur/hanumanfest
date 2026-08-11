<?php

namespace App\Command;

use App\Entity\FaqItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\SitePage;
use App\Service\Content\WpContentCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cms:clean-wp-markup', description: 'Remove WordPress block comments from CMS text fields')]
final class CleanWpMarkupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WpContentCleaner $wpContentCleaner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updated = 0;

        foreach ($this->em->getRepository(HomeHero::class)->findAll() as $hero) {
            $clean = $this->wpContentCleaner->cleanHtml($hero->getAboutHtml());
            if ($clean !== $hero->getAboutHtml()) {
                $hero->setAboutHtml($clean);
                ++$updated;
            }
        }

        foreach ($this->em->getRepository(Person::class)->findAll() as $person) {
            $excerpt = $this->wpContentCleaner->cleanPlain($person->getExcerpt());
            $bio = $this->wpContentCleaner->cleanHtml($person->getBio());
            if ($excerpt !== $person->getExcerpt() || $bio !== $person->getBio()) {
                $person->setExcerpt($excerpt)->setBio($bio);
                ++$updated;
            }
        }

        foreach ($this->em->getRepository(InfoBlock::class)->findAll() as $block) {
            $content = $this->wpContentCleaner->cleanHtml($block->getContent());
            if ($content !== $block->getContent()) {
                $block->setContent($content);
                ++$updated;
            }
        }

        foreach ($this->em->getRepository(FaqItem::class)->findAll() as $faq) {
            $answer = $this->wpContentCleaner->cleanHtml($faq->getAnswer());
            if ($answer !== $faq->getAnswer()) {
                $faq->setAnswer($answer);
                ++$updated;
            }
        }

        foreach ($this->em->getRepository(Review::class)->findAll() as $review) {
            $body = $this->wpContentCleaner->cleanHtml($review->getBody());
            if ($body !== $review->getBody()) {
                $review->setBody($body);
                ++$updated;
            }
        }

        foreach ($this->em->getRepository(SitePage::class)->findAll() as $page) {
            $content = $this->wpContentCleaner->cleanHtml($page->getContentHtml()) ?? '';
            if ($content !== $page->getContentHtml()) {
                $page->setContentHtml($content);
                ++$updated;
            }
        }

        $this->em->flush();
        $io->success(sprintf('Обновлено записей: %d', $updated));

        return Command::SUCCESS;
    }
}
