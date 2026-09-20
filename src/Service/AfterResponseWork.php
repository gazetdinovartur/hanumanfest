<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Работа, которую можно сделать после ответа клиенту (Sheets, письмо).
 */
final class AfterResponseWork
{
    /** @var list<callable(): void> */
    private array $jobs = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable(): void $job
     */
    public function add(callable $job): void
    {
        if ($this->requestStack->getMainRequest() === null) {
            $this->runJob($job);

            return;
        }

        $this->jobs[] = $job;
    }

    public function run(): void
    {
        $this->releaseHttpClient();

        $jobs = $this->jobs;
        $this->jobs = [];
        foreach ($jobs as $job) {
            $this->runJob($job);
        }
    }

    /**
     * Symfony Runtime при APP_DEBUG=1 не вызывает fastcgi_finish_request,
     * а nginx ждёт закрытия FastCGI — Sheets в TERMINATE тогда блокирует ответ.
     */
    private function releaseHttpClient(): void
    {
        if (\PHP_SAPI !== 'fpm-fcgi' && \PHP_SAPI !== 'litespeed') {
            return;
        }

        ignore_user_abort(true);

        if (\PHP_SAPI === 'fpm-fcgi' && \function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();

            return;
        }
        if (\PHP_SAPI === 'litespeed' && \function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }

    /**
     * @param callable(): void $job
     */
    private function runJob(callable $job): void
    {
        try {
            $job();
        } catch (\Throwable $e) {
            $this->logger->error('After-response work failed', ['error' => $e->getMessage()]);
        }
    }
}
