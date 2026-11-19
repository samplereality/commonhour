<?php
declare(strict_types=1);

namespace App;

/** Signed cookies, CSRF tokens and the keyed hashes used for rate limiting. */
final class Security
{
    private const CSRF_COOKIE = 'ch_csrf';

    public function __construct(private string $secret, private string $cookiePath = '/')
    {
    }

    /** The site's signing key: generated once and kept beside the database. */
    public static function loadSecret(string $dataDir): string
    {
        $file = $dataDir . '/secret.key';
        if (!is_file($file)) {
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0700, true);
            }
            file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
            @chmod($file, 0600);
        }
        return trim((string) file_get_contents($file));
    }

    public function sign(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), '+/', '-_'), '=');
    }

    // ----- CSRF: a random cookie, and a token derived from it that pages embed -----

    public function csrfToken(): string
    {
        $cookie = $_COOKIE[self::CSRF_COOKIE] ?? '';
        if (!is_string($cookie) || !preg_match('/^[0-9a-f]{32}$/', $cookie)) {
            $cookie = bin2hex(random_bytes(16));
            $this->setCookie(self::CSRF_COOKIE, $cookie, 0);
        }
        return $this->sign('csrf|' . $cookie);
    }

    public function checkCsrf(): bool
    {
        $cookie = $_COOKIE[self::CSRF_COOKIE] ?? '';
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($cookie) && $cookie !== '' && is_string($token)
            && hash_equals($this->sign('csrf|' . $cookie), $token);
    }

    // ----- Per-event sign-in: "<response id>.<expiry>.<signature>" -----

    public function startSession(string $slug, int $responseId, int $days = 180): void
    {
        $expires = time() + $days * 86400;
        $value = $responseId . '.' . $expires . '.' . $this->sign("session|$slug|$responseId|$expires");
        $this->setCookie(self::sessionCookie($slug), $value, $expires);
    }

    /** The signed-in response id for this event, or null. */
    public function session(string $slug): ?int
    {
        $value = $_COOKIE[self::sessionCookie($slug)] ?? '';
        if (!is_string($value) || !preg_match('/^(\d+)\.(\d+)\.([\w-]+)$/', $value, $m)) {
            return null;
        }
        [, $id, $expires, $signature] = $m;
        if ((int) $expires < time() || !hash_equals($this->sign("session|$slug|$id|$expires"), $signature)) {
            return null;
        }
        return (int) $id;
    }

    public function endSession(string $slug): void
    {
        $this->setCookie(self::sessionCookie($slug), '', time() - 3600);
    }

    /** A stable, non-reversible key for the visitor's address. */
    public function clientKey(): string
    {
        return substr($this->sign('ip|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')), 0, 22);
    }

    private static function sessionCookie(string $slug): string
    {
        return 'ch_s_' . $slug;
    }

    private function setCookie(string $name, string $value, int $expires): void
    {
        // Keep this request's own view of the cookie in step with what is sent.
        if ($value === '') {
            unset($_COOKIE[$name]);
        } else {
            $_COOKIE[$name] = $value;
        }
        if (headers_sent()) {
            return;
        }
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => $this->cookiePath,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
