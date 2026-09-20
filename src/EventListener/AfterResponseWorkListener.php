<?php

namespace App\EventListener;

use App\Service\AfterResponseWork;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::TERMINATE, priority: 1024)]
final class AfterResponseWorkListener
{
    public function __construct(private readonly AfterResponseWork $afterResponseWork)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $this->afterResponseWork->run();
    }
}
