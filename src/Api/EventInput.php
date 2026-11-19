<?php
declare(strict_types=1);

namespace App\Api;

use App\Themes;
use App\TimeZones;

/** Checks and normalizes the fields of a new event. */
final class EventInput
{
    public const MAX_TITLE = 120;

    /** @return array validated fields ready for Events::create */
    public static function validate(array $in, array $config, Themes $themes): array
    {
        $title = self::title($in['title'] ?? null);

        $slot = $in['slot_minutes'] ?? null;
        if (!in_array($slot, [15, 30], true)) {
            throw new ApiError('slot');
        }

        $start = $in['start_minute'] ?? null;
        $end = $in['end_minute'] ?? null;
        if (!is_int($start) || !is_int($end) || $start < 0 || $end > 1440 || $start >= $end
            || $start % $slot !== 0 || $end % $slot !== 0) {
            throw new ApiError('times');
        }

        if (!TimeZones::valid($in['timezone'] ?? null)) {
            throw new ApiError('timezone');
        }

        $mode = $in['mode'] ?? null;
        $days = self::days($mode, $in['days'] ?? null, (int) $config['limits']['max_days']);
        $theme = self::theme($in['theme'] ?? null, $themes);

        return [
            'title' => $title,
            'mode' => $mode,
            'days' => $days,
            'start_minute' => $start,
            'end_minute' => $end,
            'slot_minutes' => $slot,
            'timezone' => $in['timezone'],
            'theme' => $theme,
        ];
    }

    public static function title(mixed $value): string
    {
        $title = self::cleanText($value);
        if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
            throw new ApiError('title', 422, ['max' => self::MAX_TITLE]);
        }
        return $title;
    }

    /** ISO dates or weekday numbers, de-duplicated and sorted. */
    public static function days(mixed $mode, mixed $days, int $maxDays): array
    {
        if (!is_array($days) || $days === []) {
            throw new ApiError('days');
        }
        return match ($mode) {
            'dates' => self::dates($days, $maxDays),
            'weekdays' => self::weekdays($days),
            default => throw new ApiError('mode'),
        };
    }

    /** A theme folder name, or null for the site default. */
    public static function theme(mixed $value, Themes $themes): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (!is_string($value) || !$themes->exists($value)) {
            throw new ApiError('theme');
        }
        return $value;
    }

    /** Trim, and collapse runs of whitespace (including line breaks) to one space. */
    public static function cleanText(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';
    }

    private static function dates(array $days, int $maxDays): array
    {
        $out = [];
        foreach ($days as $day) {
            if (!is_string($day) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $m)
                || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                throw new ApiError('days');
            }
            $out[$day] = true;
        }
        $out = array_keys($out);
        sort($out);
        $span = (new \DateTimeImmutable($out[0]))->diff(new \DateTimeImmutable(end($out)))->days + 1;
        if ($span > $maxDays) {
            throw new ApiError('range', 422, ['max' => $maxDays]);
        }
        return $out;
    }

    private static function weekdays(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            if (!is_int($day) || $day < 0 || $day > 6) {
                throw new ApiError('days');
            }
            $out[$day] = true;
        }
        $out = array_keys($out);
        sort($out);
        return $out;
    }
}
