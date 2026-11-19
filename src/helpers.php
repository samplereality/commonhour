<?php
declare(strict_types=1);

// Template helpers. Templates should echo only through e() or t().

use App\View;

/** Escape a value for HTML text or an attribute. */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A string from the copy file, already escaped. t('home.title'), t('x.y', ['n' => 3]). */
function t(string $key, array $vars = []): string
{
    return e(View::current()->text($key, $vars));
}

/** A list from the copy file (not escaped; escape each item). */
function copy_list(string $key): array
{
    return View::current()->textList($key);
}

/** URL of a page in this app, e.g. url('/styleguide'). */
function url(string $path = '/'): string
{
    return View::current()->url($path);
}

/** URL of a file in public/assets, with a cache-busting version. */
function asset(string $file): string
{
    return View::current()->asset($file);
}

/** URL of a file in the active theme's folder. */
function theme_url(string $file): string
{
    return View::current()->themeUrl($file);
}

/** Render templates/partials/<name>.php in place. */
function partial(string $name, array $data = []): void
{
    echo View::current()->partial($name, $data);
}

/** The token scripts must send back with every write. */
function csrf_token(): string
{
    $csrf = View::current()->csrf;
    return $csrf ? $csrf() : '';
}

/** Data for the page's scripts, as a JSON block that is safe inside HTML. */
function json_block(string $id, mixed $data): void
{
    echo '<script type="application/json" id="', e($id), '">',
        json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        '</script>';
}

/** Minutes after midnight as a clock label: 540 -> "9 AM", 570 -> "9:30 AM". */
function format_minute(int $minute, bool $short = false): string
{
    $h = intdiv($minute, 60) % 24;
    $m = $minute % 60;
    $suffix = $h < 12 ? 'AM' : 'PM';
    $h12 = $h % 12 === 0 ? 12 : $h % 12;
    if ($short && $m === 0) {
        return $h12 . ' ' . $suffix;
    }
    return sprintf('%d:%02d %s', $h12, $m, $suffix);
}
