<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final class ApiRateLimitListener
{
    public function __construct(
        private readonly string $environment,
        private readonly RateLimiterFactory $apiPublicLimiter,
        private readonly RateLimiterFactory $apiWriteLimiter,
        private readonly RateLimiterFactory $apiWebhookLimiter,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->environment === 'test') {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return;
        }

        if ($path === '/api/health') {
            return;
        }

        $clientIp = $request->getClientIp() ?? 'unknown';

        if ($path === '/api/webhooks/yookassa') {
            $limiter = $this->apiWebhookLimiter->create($clientIp);
        } elseif ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $limiter = $this->apiWriteLimiter->create($clientIp);
        } else {
            $limiter = $this->apiPublicLimiter->create($clientIp);
        }

        if ($limiter->consume()->isAccepted()) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'Слишком много запросов. Попробуйте позже.'],
            Response::HTTP_TOO_MANY_REQUESTS,
        ));
    }
}
