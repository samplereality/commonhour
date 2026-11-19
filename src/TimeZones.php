<?php
declare(strict_types=1);

namespace App;

final class TimeZones
{
    /** @return string[] every IANA zone name PHP knows, plus UTC */
    public static function all(): array
    {
        static $all = null;
        return $all ??= \DateTimeZone::listIdentifiers();
    }

    public static function valid(mixed $name): bool
    {
        return is_string($name) && in_array($name, self::all(), true);
    }
}
