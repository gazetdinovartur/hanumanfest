<?php

namespace App\Tests\Unit\Service;

use App\Service\AfterResponseWork;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('unit')]
final class AfterResponseWorkTest extends TestCase
{
    public function testRunsImmediatelyWithoutHttpRequest(): void
    {
        $ran = false;
        $work = new AfterResponseWork(new RequestStack(), new NullLogger());
        $work->add(static function () use (&$ran): void {
            $ran = true;
        });

        self::assertTrue($ran);
    }

    public function testDefersUntilRunDuringHttpRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api/applications', 'POST'));
        $ran = false;
        $work = new AfterResponseWork($stack, new NullLogger());
        $work->add(static function () use (&$ran): void {
            $ran = true;
        });

        self::assertFalse($ran);
        $work->run();
        self::assertTrue($ran);
    }

    public function testRunDoesNotFlushCliOutputBuffers(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api/applications', 'POST'));
        $work = new AfterResponseWork($stack, new NullLogger());
        $work->add(static function (): void {});

        ob_start();
        $level = ob_get_level();
        $work->run();
        self::assertSame($level, ob_get_level());
        ob_end_clean();
    }
}
