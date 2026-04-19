<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\StatusMonitorService;
use PHPUnit\Framework\TestCase;

final class StatusMonitorServiceTest extends TestCase
{
    public function testDowndetectorNoCurrentProblemsWinsOverHistoricProblemsHeading(): void
    {
        $service = (new \ReflectionClass(StatusMonitorService::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'detectDowndetectorState');

        $state = $method->invoke(
            $service,
            mb_strtolower('User reports show no current problems with Tele2. Tele2 problems reported in the last 24 hours.'),
        );

        self::assertSame('green', $state);
    }

    public function testDowndetectorIndicatedProblemsBecomeYellow(): void
    {
        $service = (new \ReflectionClass(StatusMonitorService::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'detectDowndetectorState');

        $state = $method->invoke(
            $service,
            mb_strtolower('User reports indicate possible problems at Tele2.'),
        );

        self::assertSame('yellow', $state);
    }

    public function testIsItDownRightNowServerUpBecomesGreen(): void
    {
        $service = (new \ReflectionClass(StatusMonitorService::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'detectIsItDownRightNowState');

        $state = $method->invoke(
            $service,
            mb_strtolower('Outlook.com Server Status Check Server is up. Last checked 11 hours 29 mins ago.'),
        );

        self::assertSame('green', $state);
    }

    public function testIsItDownRightNowServerDownBecomesRed(): void
    {
        $service = (new \ReflectionClass(StatusMonitorService::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'detectIsItDownRightNowState');

        $state = $method->invoke(
            $service,
            mb_strtolower('Live.com - Windows Live Hotmail Server is down. Last checked 5 mins ago.'),
        );

        self::assertSame('red', $state);
    }

    public function testCloudflareChallengePageIsDetected(): void
    {
        $service = (new \ReflectionClass(StatusMonitorService::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'isCloudflareChallengePage');

        $state = $method->invoke(
            $service,
            mb_strtolower('Just a moment... Enable JavaScript and cookies to continue window._cf_chl_opt'),
        );

        self::assertTrue($state);
    }
}
