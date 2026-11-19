<?php
declare(strict_types=1);

// Fails (exit code 1) if a visual value appears outside themes/.
//
//   php bin/check-tokens.php
//
// Everywhere:      raw colors and inline styles.
// In stylesheets:  also lengths, durations, font names, sizes and weights.
//
// A line may opt out with the comment  /* token-check: allow */  (used for the
// few accessibility guardrails that must not depend on a theme).

$root = dirname(__DIR__);
$scan = ['public', 'templates', 'src', 'copy'];
$extensions = ['css', 'js', 'php', 'html', 'svg'];

$hex = '/(?<![&\w])#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b/i';
$colorFn = '/\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(/';
$length = '/(?<![\w.#-])(?:\d*\.)?\d+(?:px|rem|em|pt|ch|ms|s)\b/';
$named = '/(?<![\w-])(?:black|white|red|green|blue|gray|grey|yellow|orange|purple|pink|brown|silver|navy|teal|maroon|olive|lime|aqua|fuchsia|gold|beige|ivory|tan|coral|salmon|crimson|indigo|violet|magenta|cyan)(?![\w-])/i';
$tokenOnly = '/^(?:var\(--[\w-]+\)|inherit|initial|unset)(?:\s*!important)?$/';

$problems = [];
$files = 0;

foreach ($scan as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) {
            continue;
        }
        $files++;
        $rel = substr($file->getPathname(), strlen($root) + 1);
        $inComment = false;

        foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $i => $raw) {
            if (str_contains($raw, 'token-check: allow')) {
                continue;
            }
            $report = static function (string $what) use (&$problems, $rel, $i, $raw): void {
                $problems[] = sprintf('%s:%d  %s  ->  %s', $rel, $i + 1, $what, trim($raw));
            };

            // Drop /* ... */ comments, which may span lines.
            $line = '';
            $rest = $raw;
            while ($rest !== '') {
                if ($inComment) {
                    $end = strpos($rest, '*/');
                    if ($end === false) {
                        $rest = '';
                    } else {
                        $rest = substr($rest, $end + 2);
                        $inComment = false;
                    }
                } else {
                    $open = strpos($rest, '/*');
                    if ($open === false) {
                        $line .= $rest;
                        $rest = '';
                    } else {
                        $line .= substr($rest, 0, $open);
                        $rest = substr($rest, $open + 2);
                        $inComment = true;
                    }
                }
            }

            if (preg_match($hex, $line)) {
                $report('raw hex color');
            }
            if (preg_match($colorFn, $line)) {
                $report('raw color function');
            }
            if ($ext !== 'css' && $ext !== 'js' && preg_match('/\sstyle\s*=\s*["\']/i', $line)) {
                $report('inline style attribute');
            }
            if ($ext === 'js' && preg_match('/\.style\b|\.cssText\b/', $line)) {
                $report('script sets an inline style');
            }
            if ($ext !== 'css') {
                continue;
            }

            // Breakpoints cannot be tokens, so @media conditions are exempt.
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '@') || str_ends_with($trimmed, '{')) {
                continue;
            }
            if (preg_match($length, $line)) {
                $report('raw length or duration');
            }
            if (!preg_match('/^([a-z-]+)\s*:\s*(.+?);?$/i', $trimmed, $m)) {
                continue;
            }
            [, $property, $value] = $m;
            $property = strtolower($property);
            if (in_array($property, ['font', 'font-family', 'font-size', 'font-weight', 'line-height'], true)
                && !preg_match($tokenOnly, $value) && $value !== '0') {
                $report("$property must be a token");
            }
            if (preg_match($named, preg_replace('/var\(--[\w-]+\)/', '', $value))) {
                $report('named color');
            }
        }
    }
}

if ($problems) {
    echo implode("\n", $problems), "\n\n", count($problems), " problem(s). Move these values into themes/<name>/theme.css as tokens.\n";
    exit(1);
}

echo "OK: $files files checked, no raw visual values outside themes/.\n";
