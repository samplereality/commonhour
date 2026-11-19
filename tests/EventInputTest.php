<?php
declare(strict_types=1);

namespace Tests;

use App\Api\ApiError;
use App\Api\EventInput;
use App\Themes;
use PHPUnit\Framework\TestCase;

final class EventInputTest extends TestCase
{
    private const CONFIG = ['limits' => ['max_days' => 60]];

    private static function valid(array $over = []): array
    {
        return $over + [
            'title' => 'Reading group', 'mode' => 'dates', 'days' => ['2026-12-07', '2026-12-08'],
            'start_minute' => 540, 'end_minute' => 1020, 'slot_minutes' => 30, 'timezone' => 'America/New_York',
        ];
    }

    private static function check(array $in): array
    {
        return EventInput::validate($in, self::CONFIG, new Themes(dirname(__DIR__) . '/themes', 'default'));
    }

    /** The error code validation fails with, or null if it passes. */
    private static function errorFor(array $over): ?string
    {
        try {
            self::check(self::valid($over));
            return null;
        } catch (ApiError $e) {
            return $e->errorCode;
        }
    }

    public function testAValidEventIsNormalized(): void
    {
        $out = self::check(self::valid(['title' => "  Reading \n group ", 'days' => ['2026-12-08', '2026-12-07', '2026-12-08']]));
        $this->assertSame('Reading group', $out['title']);
        $this->assertSame(['2026-12-07', '2026-12-08'], $out['days']);
        $this->assertNull($out['theme']);
    }

    public function testWeekdaysAreSortedAndUnique(): void
    {
        $out = self::check(self::valid(['mode' => 'weekdays', 'days' => [5, 1, 5]]));
        $this->assertSame([1, 5], $out['days']);
    }

    public function testBadInputIsRejectedWithASpecificCode(): void
    {
        $this->assertNull(self::errorFor([]));
        $this->assertSame('title', self::errorFor(['title' => '   ']));
        $this->assertSame('title', self::errorFor(['title' => str_repeat('x', 121)]));
        $this->assertSame('slot', self::errorFor(['slot_minutes' => 20]));
        $this->assertSame('times', self::errorFor(['start_minute' => 1020, 'end_minute' => 540]));
        $this->assertSame('times', self::errorFor(['start_minute' => 545]));
        $this->assertSame('times', self::errorFor(['end_minute' => 1500]));
        $this->assertSame('timezone', self::errorFor(['timezone' => 'Mars/Olympus']));
        $this->assertSame('mode', self::errorFor(['mode' => 'months']));
        $this->assertSame('days', self::errorFor(['days' => []]));
        $this->assertSame('days', self::errorFor(['days' => ['2026-02-30']]));
        $this->assertSame('days', self::errorFor(['mode' => 'weekdays', 'days' => [7]]));
        $this->assertSame('theme', self::errorFor(['theme' => 'no-such-theme']));
    }

    public function testTheDateRangeIsCapped(): void
    {
        $this->assertNull(self::errorFor(['days' => ['2026-12-01', '2027-01-29']]), '60 days inclusive is allowed');
        $this->assertSame('range', self::errorFor(['days' => ['2026-12-01', '2027-01-30']]));
        $this->assertNull(self::errorFor(['end_minute' => 1440]), 'midnight is a valid end');
    }
}
