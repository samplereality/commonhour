<?php
declare(strict_types=1);

namespace App;

/** Finds themes in themes/, serves their files, and reads their tokens for the style guide. */
final class Themes
{
    private const SERVABLE = [
        'css' => 'text/css; charset=utf-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    public function __construct(private string $dir, private string $default)
    {
    }

    /** @return string[] theme folder names, default first */
    public function all(): array
    {
        $names = [];
        foreach (glob($this->dir . '/*/theme.css') ?: [] as $file) {
            $name = basename(dirname($file));
            if ($this->validName($name)) {
                $names[] = $name;
            }
        }
        usort($names, fn ($a, $b) => [$a !== $this->default, $a] <=> [$b !== $this->default, $b]);
        return $names;
    }

    public function exists(?string $name): bool
    {
        return $name !== null && $this->validName($name) && is_file("$this->dir/$name/theme.css");
    }

    /** The requested theme if it exists, otherwise the site default. */
    public function resolve(?string $name): string
    {
        return $this->exists($name) ? $name : $this->default;
    }

    public function version(string $name, string $file = 'theme.css'): int
    {
        return (int) @filemtime("$this->dir/$name/$file");
    }

    /** Send one file from a theme folder. Returns false if it may not be served. */
    public function serve(string $name, string $file): bool
    {
        if (!$this->exists($name)) {
            return false;
        }
        $base = realpath("$this->dir/$name");
        $path = realpath("$base/$file");
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (
            $path === false
            || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)
            || !is_file($path)
            || !isset(self::SERVABLE[$ext])
            || preg_match('#(^|/)\.#', $file)
        ) {
            return false;
        }

        // Always revalidate, so a theme edit shows up on the next reload.
        $etag = '"' . filemtime($path) . '-' . filesize($path) . '"';
        header('Content-Type: ' . self::SERVABLE[$ext]);
        header('Cache-Control: no-cache');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return true;
        }
        header('Content-Length: ' . filesize($path));
        readfile($path);
        return true;
    }

    /**
     * Tokens declared in a theme, grouped by its "@group" comments.
     * Only the first declaration of each token counts (the light-mode value);
     * the style guide's script fills in the value currently in effect.
     *
     * @return array<string, array<int, array{name: string, value: string}>>
     */
    public function tokens(string $name): array
    {
        $groups = [];
        $seen = [];
        $group = 'Tokens';
        foreach (file("$this->dir/$name/theme.css", FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('~/\*\s*@group\s+(.+?)\s*\*/~', $line, $m)) {
                $group = $m[1];
                continue;
            }
            if (preg_match('/^\s*(--[a-z0-9-]+)\s*:\s*(.+?)\s*;/i', $line, $m) && !isset($seen[$m[1]])) {
                $seen[$m[1]] = true;
                $groups[$group][] = ['name' => $m[1], 'value' => $m[2]];
            }
        }
        return $groups;
    }

    private function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', $name);
    }
}
