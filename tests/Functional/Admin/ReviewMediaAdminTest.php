<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Review;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class ReviewMediaAdminTest extends WebTestCase
{
    public function testReviewMediaUploadReturnsJsonWithoutRequiringGd(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $review = (new Review())->setAuthorName('Тест')->setBody('Текст отзыва')->setPublished(true);
        $em->persist($review);
        $em->flush();

        $crawler = $client->request('GET', sprintf('/admin/review/%d/edit', $review->getId()));
        self::assertResponseIsSuccessful();

        $root = $crawler->filter('[data-review-media]');
        self::assertGreaterThan(0, $root->count());
        $csrf = $root->attr('data-csrf');
        $uploadUrl = $root->attr('data-upload-url');
        self::assertNotEmpty($csrf);
        self::assertNotEmpty($uploadUrl);

        $tmp = $this->tinyJpeg();
        $client->request(
            'POST',
            (string) $uploadUrl,
            files: ['files' => [new UploadedFile($tmp, 'sample.jpg', 'image/jpeg', null, true)]],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REVIEW_MEDIA_TOKEN' => (string) $csrf,
            ],
        );

        $content = (string) $client->getResponse()->getContent();
        self::assertJson($content);
        $payload = json_decode($content, true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok'] ?? false, (string) ($payload['error'] ?? $content));
        self::assertSame(1, $payload['count'] ?? 0);
        self::assertStringNotContainsString('GD', $content);

        $path = $payload['items'][0]['path'] ?? '';
        self::assertIsString($path);
        self::assertStringStartsWith('/uploads/reviews/media/', $path);
        $this->cleanupPublicUpload($client, $path);
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return $client;
    }

    private function bootSchema(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
    }

    private function tinyJpeg(): string
    {
        $path = sys_get_temp_dir().'/hf-rev-'.bin2hex(random_bytes(4)).'.jpg';
        if (\function_exists('imagecreatetruecolor') && \function_exists('imagejpeg')) {
            $img = imagecreatetruecolor(16, 16);
            imagejpeg($img, $path, 80);
            unset($img);

            return $path;
        }

        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
            true,
        ));

        return $path;
    }

    private function cleanupPublicUpload(KernelBrowser $client, string $publicPath): void
    {
        $absolute = $client->getKernel()->getProjectDir().'/public'.$publicPath;
        @unlink($absolute);
        $base = preg_replace('/\.[^.]+$/', '', $absolute) ?? $absolute;
        foreach (['.webp', '-thumb.webp', '-card.webp'] as $suffix) {
            @unlink($base.$suffix);
        }
    }
}
