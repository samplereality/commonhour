<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Slot arithmetic.
 *
 * In "dates" mode a slot is the UTC epoch second at which it starts. Each
 * date is converted on its own: we find the instants of the day's earliest
 * and latest wall-clock times in the event's zone and step between them in
 * real elapsed time. On a day when the clocks change, that yields every slot
 * that really happens (an extra hour's worth in autumn, one fewer in spring)
 * with nothing missing and nothing doubled.
 *
 * In "weekdays" mode there is no date to anchor a conversion, so a slot is
 * the string "weekday:minute" in the event's own zone.
 */
final class Slots
{
    /**
     * @param string[] $dates ISO dates (YYYY-MM-DD)
     * @return int[] slot starts as UTC epoch seconds, ascending
     */
    public static function epochs(array $dates, int $startMinute, int $endMinute, int $slotMinutes, string $timezone): array
    {
        $zone = new DateTimeZone($timezone);
        $step = $slotMinutes * 60;
        $out = [];
        foreach ($dates as $date) {
            $midnight = new DateTimeImmutable($date . ' 00:00:00', $zone);
            $from = self::wallClock($midnight, $startMinute)->getTimestamp();
            $to = self::wallClock($midnight, $endMinute)->getTimestamp();
            for ($t = $from; $t < $to; $t += $step) {
                $out[$t] = true;
            }
        }
        ksort($out);
        return array_keys($out);
    }

    /**
     * @param int[] $weekdays 0 (Sunday) to 6
     * @return string[] "weekday:minute" keys
     */
    public static function weekdayKeys(array $weekdays, int $startMinute, int $endMinute, int $slotMinutes): array
    {
        $out = [];
        foreach ($weekdays as $weekday) {
            for ($m = $startMinute; $m < $endMinute; $m += $slotMinutes) {
                $out[] = $weekday . ':' . $m;
            }
        }
        return $out;
    }

    /** Every slot of an event row (as returned by Events::find). */
    public static function forEvent(array $event): array
    {
        return $event['mode'] === 'weekdays'
            ? self::weekdayKeys($event['days'], $event['start_minute'], $event['end_minute'], $event['slot_minutes'])
            : self::epochs($event['days'], $event['start_minute'], $event['end_minute'], $event['slot_minutes'], $event['timezone']);
    }

    /**
     * Keep only the submitted slots that belong to the event, in event order.
     *
     * @param array<int, mixed> $submitted
     */
    public static function sanitize(array $event, array $submitted): array
    {
        $wanted = [];
        foreach ($submitted as $slot) {
            if (is_int($slot) || is_string($slot)) {
                $wanted[(string) $slot] = true;
            }
        }
        return array_values(array_filter(
            self::forEvent($event),
            static fn ($slot) => isset($wanted[(string) $slot])
        ));
    }

    /** The given wall-clock minute of a day; 1440 means the following midnight. */
    private static function wallClock(DateTimeImmutable $midnight, int $minute): DateTimeImmutable
    {
        if ($minute >= 1440) {
            return $midnight->modify('+1 day');
        }
        return $midnight->setTime(intdiv($minute, 60), $minute % 60);
    }
}
