<?php
declare(strict_types=1);

namespace App;

/** Renders PHP templates and looks up copy strings. */
final class View
{
    private static ?View $current = null;

    /** Set by App: returns the CSRF token for the current visitor. */
    public ?\Closure $csrf = null;

    /** @param array<string, mixed> $shared variables every template receives */
    public function __construct(
        private string $templateDir,
        private string $publicDir,
        private array $copy,
        private string $base,
        private Themes $themes,
        private string $theme,
        private array $shared = [],
    ) {
        self::$current = $this;
    }

    public static function current(): View
    {
        return self::$current ?? throw new \LogicException('No view has been created yet.');
    }

    public function setTheme(string $theme): void
    {
        $this->theme = $theme;
    }

    /** Render templates/<template>.php inside templates/layout.php. */
    public function page(string $template, array $data = []): string
    {
        $data += ['page' => $template, 'title' => null, 'noindex' => false];
        $data['content'] = $this->include($template, $data);
        return $this->include('layout', $data);
    }

    public function partial(string $name, array $data = []): string
    {
        return $this->include('partials/' . $name, $data);
    }

    public function text(string $key, array $vars = []): string
    {
        $value = $this->lookup($key);
        if (!is_string($value)) {
            return $key; // a missing string shows its key, which is easy to spot and fix
        }
        $vars += ['site' => $this->shared['site_name'] ?? ''];
        return preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static fn (array $m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0],
            $value
        );
    }

    public function textList(string $key): array
    {
        $value = $this->lookup($key);
        return is_array($value) ? $value : [];
    }

    public function url(string $path = '/'): string
    {
        return $this->base . '/' . ltrim($path, '/');
    }

    public function asset(string $file): string
    {
        $version = (int) @filemtime($this->publicDir . '/assets/' . $file);
        return $this->url('/assets/' . $file) . '?v=' . $version;
    }

    public function themeUrl(string $file): string
    {
        return $this->url("/theme/$this->theme/$file") . '?v=' . $this->themes->version($this->theme, $file);
    }

    private function lookup(string $key): mixed
    {
        $value = $this->copy;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private function include(string $template, array $data): string
    {
        $file = $this->templateDir . '/' . $template . '.php';
        $vars = $data + $this->shared + ['theme' => $this->theme];
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
