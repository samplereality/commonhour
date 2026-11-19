<?php
declare(strict_types=1);

namespace Tests;

use App\Slots;
use PHPUnit\Framework\TestCase;

final class SlotsTest extends TestCase
{
    private static function local(int $epoch, string $zone): string
    {
        return (new \DateTimeImmutable('@' . $epoch))->setTimezone(new \DateTimeZone($zone))->format('Y-m-d H:i');
    }

    public function testAnOrdinaryDayHasOneSlotPerStep(): void
    {
        $slots = Slots::epochs(['2026-12-07'], 540, 1020, 30, 'America/New_York');
        $this->assertCount(16, $slots);
        $this->assertSame('2026-12-07 09:00', self::local($slots[0], 'America/New_York'));
        $this->assertSame('2026-12-07 16:30', self::local($slots[15], 'America/New_York'));

        $this->assertCount(32, Slots::epochs(['2026-12-07'], 540, 1020, 15, 'America/New_York'));
    }

    public function testTheSameInstantReadsCorrectlyInRomeAndTokyo(): void
    {
        [$first] = Slots::epochs(['2026-12-07'], 540, 600, 30, 'America/New_York');
        $this->assertSame('2026-12-07 14:00', self::local($first, 'UTC'));
        $this->assertSame('2026-12-07 15:00', self::local($first, 'Europe/Rome'));
        $this->assertSame('2026-12-07 23:00', self::local($first, 'Asia/Tokyo'));

        // An evening slot in New York falls on the next calendar day in Tokyo.
        [$evening] = Slots::epochs(['2026-12-07'], 1200, 1230, 30, 'America/New_York');
        $this->assertSame('2026-12-08 10:00', self::local($evening, 'Asia/Tokyo'));
    }

    public function testWorkingHoursStayPutAcrossTheNovemberClockChange(): void
    {
        // Clocks go back in New York on 1 November 2026.
        $dates = ['2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02', '2026-11-03'];
        $slots = Slots::epochs($dates, 540, 1020, 30, 'America/New_York');

        $this->assertCount(5 * 16, $slots);
        $this->assertCount(5 * 16, array_unique($slots), 'no duplicated slots');
        foreach ($dates as $i => $date) {
            $day = array_slice($slots, $i * 16, 16);
            $this->assertSame("$date 09:00", self::local($day[0], 'America/New_York'));
            $this->assertSame("$date 16:30", self::local($day[15], 'America/New_York'));
            for ($n = 1; $n < 16; $n++) {
                $this->assertSame(1800, $day[$n] - $day[$n - 1], 'no gaps within a day');
            }
        }
        // The UTC offset really did change between the first and last day.
        $this->assertSame('13:00', gmdate('H:i', $slots[0]));
        $this->assertSame('14:00', gmdate('H:i', $slots[4 * 16]));
    }

    public function testAFullDayOnTheClockChangeHasEveryRealSlotOnce(): void
    {
        // 25 hours long: the hour from 1:00 to 2:00 happens twice.
        $autumn = Slots::epochs(['2026-11-01'], 0, 1440, 30, 'America/New_York');
        $this->assertCount(50, $autumn);
        $this->assertCount(50, array_unique($autumn));
        $this->assertSame(25 * 3600 - 1800, end($autumn) - $autumn[0]);

        // 23 hours long: 2:00 to 3:00 never happens.
        $spring = Slots::epochs(['2026-03-08'], 0, 1440, 30, 'America/New_York');
        $this->assertCount(46, $spring);
        $this->assertCount(46, array_unique($spring));

        // Three whole days around the change form one unbroken run.
        $run = Slots::epochs(['2026-10-31', '2026-11-01', '2026-11-02'], 0, 1440, 30, 'America/New_York');
        $this->assertCount(48 + 50 + 48, $run);
        for ($n = 1; $n < count($run); $n++) {
            $this->assertSame(1800, $run[$n] - $run[$n - 1]);
        }
    }

    public function testWeekdaySlotsAreNeverConverted(): void
    {
        $this->assertSame(
            ['1:540', '1:570', '3:540', '3:570'],
            Slots::weekdayKeys([1, 3], 540, 600, 30)
        );
    }

    public function testSanitizeKeepsOnlySlotsThatBelongToTheEvent(): void
    {
        $event = [
            'mode' => 'dates', 'days' => ['2026-12-07'], 'start_minute' => 540, 'end_minute' => 660,
            'slot_minutes' => 30, 'timezone' => 'America/New_York',
        ];
        $all = Slots::forEvent($event);
        $this->assertSame(
            [$all[0], $all[2]],
            Slots::sanitize($event, [$all[2], 12345, 'junk', $all[0], $all[0], ['x'], null])
        );

        $weekly = ['mode' => 'weekdays', 'days' => [1], 'start_minute' => 540, 'end_minute' => 600, 'slot_minutes' => 30, 'timezone' => 'UTC'];
        $this->assertSame(['1:570'], Slots::sanitize($weekly, ['1:570', '2:540', '1:555']));
    }
}
