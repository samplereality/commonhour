<?php
declare(strict_types=1);

namespace App;

use App\Api\ApiError;
use App\Api\EventsApi;

final class App
{
    private const MAX_BODY = 512 * 1024;

    public static function run(string $root): void
    {
        $config = Config::load($root);
        $themes = new Themes($root . '/themes', $config['default_theme']);

        // Where the app sits in the URL space, and the path within it.
        $base = $config['base_path'];
        if ($base === null) {
            $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $base = $base === '.' ? '' : $base;
        }
        $base = rtrim($base, '/');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim(rawurldecode($path), '/');

        $copy = require $root . '/copy/' . basename($config['locale']) . '.php';
        $view = new View(
            $root . '/templates',
            $root . '/public',
            $copy,
            $base,
            $themes,
            $themes->resolve(null),
            ['site_name' => $config['site_name'], 'themes' => $themes->all()],
        );

        // The database and everything that needs it are created on first use,
        // so pages like the style guide never touch it.
        $services = static function () use ($config, $root, $themes, $base): array {
            static $made = null;
            if ($made === null) {
                $db = Db::open($config['data_path'], $root . '/schema.sql');
                $security = new Security(Security::loadSecret(dirname($config['data_path'])), $base . '/');
                $events = new Events($db);
                $made = [
                    'security' => $security,
                    'events' => $events,
                    'api' => new EventsApi($events, $security, new RateLimiter($db), $themes, $config),
                ];
            }
            return $made;
        };
        $view->csrf = static fn (): string => $services()['security']->csrfToken();

        $html = static function (string $body, int $status = 200) use ($config): void {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Security-Policy: ' . $config['csp']);
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: same-origin');
            echo $body;
        };

        $notFound = static function () use ($view, $html): void {
            $html($view->page('not-found', ['title' => $view->text('not_found.title'), 'noindex' => true]), 404);
        };

        // Runs an API handler and sends its result, or its error, as JSON.
        $json = static function (callable $handler, bool $write = false) use ($services, $view): void {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            try {
                $body = [];
                if ($write) {
                    if (!$services()['security']->checkCsrf()) {
                        throw new ApiError('csrf', 403);
                    }
                    $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
                    if (strlen($raw) > self::MAX_BODY) {
                        throw new ApiError('too_large', 413);
                    }
                    $body = $raw === '' ? [] : json_decode($raw, true);
                    if (!is_array($body)) {
                        throw new ApiError('bad_request', 400);
                    }
                }
                [$data, $status] = $handler($services()['api'], $body);
            } catch (ApiError $e) {
                $status = $e->status;
                $data = ['error' => [
                    'code' => $e->errorCode,
                    'message' => $view->text('errors.' . $e->errorCode, $e->vars),
                ]];
            } catch (\Throwable $e) {
                error_log((string) $e);
                $status = 500;
                $data = ['error' => ['code' => 'server', 'message' => $view->text('errors.server')]];
            }
            http_response_code($status);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        };

        $router = new Router();

        // ----- Pages -----

        $router->get('/', static function () use ($view, $html, $config, $themes): void {
            $html($view->page('home', [
                'zones' => TimeZones::all(),
                'default_slot' => (int) $config['default_slot_minutes'],
                'needs_passphrase' => ($config['create_passphrase'] ?? '') !== '',
                'max_days' => (int) $config['limits']['max_days'],
                'default_theme' => $themes->resolve(null),
                'data' => ['copy' => $view->textList('js'), 'api' => $view->url('/api/events'), 'eventBase' => $view->url('/e/')],
            ]));
        });

        $router->get('/e/{slug}', static function (array $p) use ($view, $html, $notFound, $services, $themes): void {
            $event = $services()['events']->find($p['slug']);
            if (!$event) {
                $notFound();
                return;
            }
            $view->setTheme($themes->resolve($event['theme']));
            $html($view->page('event', [
                'title' => $event['title'],
                'noindex' => true,
                'event' => $event,
                'zones' => TimeZones::all(),
                'data' => $services()['api']->snapshot($event) + [
                    'copy' => $view->textList('js'),
                    'api' => $view->url('/api/events/' . $event['slug']),
                ],
            ]));
        });

        // The private link: edit, extend, remove a respondent, delete.
        $router->get('/e/{slug}/admin/{token}', static function (array $p) use ($view, $html, $notFound, $services, $themes, $config): void {
            $events = $services()['events'];
            $event = $events->find($p['slug']);
            if (!$event || !$events->isAdmin($event, $p['token'])) {
                $notFound();
                return;
            }
            $view->setTheme($themes->resolve($event['theme']));
            $html($view->page('admin', [
                'title' => $view->text('admin.title', ['title' => $event['title']]),
                'noindex' => true,
                'event' => $event,
                'default_theme' => $themes->resolve(null),
                'max_days' => (int) $config['limits']['max_days'],
                'data' => $services()['api']->adminSnapshot($event) + [
                    'copy' => $view->textList('js'),
                    'api' => $view->url('/api/events/' . $event['slug'] . '/admin'),
                    'token' => $p['token'],
                    'home' => $view->url('/'),
                ],
            ]));
        });

        $router->get('/styleguide', static function () use ($view, $themes, $html): void {
            $theme = $themes->resolve($_GET['theme'] ?? null);
            $view->setTheme($theme);
            $html($view->page('styleguide', [
                'title' => $view->text('styleguide.title'),
                'noindex' => true,
                'tokens' => $themes->tokens($theme),
                'data' => SampleEvent::build(
                    $view->textList('styleguide.sample_people'),
                    $view->text('styleguide.sample_event')
                ) + ['copy' => $view->textList('js')],
            ]));
        });

        // Helper CSS for the style guide: lets markup say data-bg="--accent"
        // to show a token as a swatch without any inline style.
        $router->get('/styleguide/tokens.css', static function () use ($themes): void {
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: no-cache');
            foreach ($themes->tokens($themes->resolve($_GET['theme'] ?? null)) as $group) {
                foreach ($group as $token) {
                    $n = $token['name'];
                    echo "[data-bg=\"$n\"]{background-color:var($n)}[data-fg=\"$n\"]{color:var($n)}\n";
                }
            }
        });

        $router->get('/theme/{name}/{file*}', static function (array $p) use ($themes, $notFound): void {
            if (!$themes->serve($p['name'], $p['file'])) {
                $notFound();
            }
        });

        // ----- API -----

        $router->post('/api/events', static function () use ($json): void {
            $json(static fn (EventsApi $api, array $in) => $api->create($in), true);
        });

        $router->get('/api/events/{slug}', static function (array $p) use ($json): void {
            $since = isset($_GET['since']) && is_string($_GET['since']) ? $_GET['since'] : null;
            $json(static fn (EventsApi $api) => $api->show($p['slug'], $since));
        });

        $router->post('/api/events/{slug}/signin', static function (array $p) use ($json): void {
            $json(static fn (EventsApi $api, array $in) => $api->signin($p['slug'], $in), true);
        });

        $router->post('/api/events/{slug}/signout', static function (array $p) use ($json): void {
            $json(static fn (EventsApi $api) => $api->signout($p['slug']), true);
        });

        $router->put('/api/events/{slug}/responses/me', static function (array $p) use ($json): void {
            $json(static fn (EventsApi $api, array $in) => $api->saveMine($p['slug'], $in), true);
        });

        foreach (['update' => 'adminUpdate', 'remove' => 'adminRemove', 'delete' => 'adminDelete'] as $action => $method) {
            $router->post("/api/events/{slug}/admin/$action", static function (array $p) use ($json, $method): void {
                $json(static fn (EventsApi $api, array $in) => $api->$method($p['slug'], $in), true);
            });
        }

        if (!$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path)) {
            if (str_starts_with($path, '/api/')) {
                $json(static fn () => throw new ApiError('not_found', 404));
            } else {
                $notFound();
            }
        }
    }
}
