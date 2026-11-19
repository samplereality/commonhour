<?php
declare(strict_types=1);

namespace App;

/**
 * The fake event shown on /styleguide, in the same shape the API returns, so
 * the style guide draws its grids with the real scripts. Six people whose
 * availability overlaps so that every count from 0 to 6 appears.
 */
final class SampleEvent
{
    // Per person: rectangles of [first weekday, last weekday, from minute, to minute).
    private const AVAILABILITY = [
        [[1, 5, 540, 630]],
        [[1, 3, 540, 660]],
        [[2, 4, 570, 690]],
        [[3, 5, 600, 720]],
        [[2, 3, 570, 660]],
        [[3, 3, 540, 720]],
    ];

    private const MINE = [[1, 2, 555, 630], [4, 4, 600, 705]];

    /** @param string[] $names six display names from the copy file */
    public static function build(array $names, string $title): array
    {
        $event = [
            'slug' => 'sample',
            'title' => $title,
            'mode' => 'weekdays',
            'days' => [1, 2, 3, 4, 5],
            'start_minute' => 540,
            'end_minute' => 720,
            'slot_minutes' => 15,
            'timezone' => 'America/New_York',
        ];
        $event['slots'] = Slots::forEvent($event);

        $responses = [];
        foreach (self::AVAILABILITY as $i => $rects) {
            $responses[] = ['name' => $names[$i] ?? 'Person ' . ($i + 1), 'slots' => self::slots($rects)];
        }

        return ['event' => $event, 'responses' => $responses, 'mine' => self::slots(self::MINE)];
    }

    /** @return string[] "weekday:minute" keys */
    private static function slots(array $rects): array
    {
        $out = [];
        foreach ($rects as [$d1, $d2, $m1, $m2]) {
            for ($d = $d1; $d <= $d2; $d++) {
                for ($m = $m1; $m < $m2; $m += 15) {
                    $out[] = "$d:$m";
                }
            }
        }
        return $out;
    }
}
