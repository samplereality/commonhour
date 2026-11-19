<?php
declare(strict_types=1);

namespace Tests;

use App\Api\ApiError;
use App\Api\EventsApi;
use App\Db;
use App\Events;
use App\RateLimiter;
use App\Security;
use App\Themes;
use PHPUnit\Framework\TestCase;

/** The API against a throwaway database. Cookies live in $_COOKIE, as in a real request. */
final class ApiTest extends TestCase
{
    private string $dir;
    private \PDO $db;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/commonhour-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->db = Db::open($this->dir . '/app.sqlite', dirname(__DIR__) . '/schema.sql');
        $_COOKIE = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
    }

    protected function tearDown(): void
    {
        unset($this->db);
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
        $_COOKIE = [];
    }

    private function api(array $config = []): EventsApi
    {
        $config = array_replace_recursive([
            'create_passphrase' => 'open sesame',
            'retention_days' => 90,
            'limits' => ['max_days' => 60, 'max_respondents' => 200, 'events_per_hour' => 10],
        ], $config);
        return new EventsApi(
            new Events($this->db),
            new Security('test-secret'),
            new RateLimiter($this->db),
            new Themes(dirname(__DIR__) . '/themes', 'default'),
            $config
        );
    }

    private static function newEvent(array $over = []): array
    {
        return $over + [
            'title' => 'Reading group', 'mode' => 'dates', 'days' => ['2026-12-07', '2026-12-08'],
            'start_minute' => 540, 'end_minute' => 660, 'slot_minutes' => 30, 'timezone' => 'America/New_York',
            'passphrase' => 'open sesame',
        ];
    }

    private static function codeOf(callable $call): ?string
    {
        try {
            $call();
            return null;
        } catch (ApiError $e) {
            return $e->errorCode;
        }
    }

    /** A second visitor: no cookies carried over. */
    private static function newBrowser(): void
    {
        $_COOKIE = [];
    }

    public function testCreatingAnEventGivesAnEmptyGridWithTheRightSlots(): void
    {
        $api = $this->api();
        [$created, $status] = $api->create(self::newEvent());
        $this->assertSame(201, $status);
        $this->assertTrue((bool) preg_match('/^[a-z0-9]{8}$/', $created['slug']));

        [$shown] = $api->show($created['slug']);
        $this->assertSame('Reading group', $shown['event']['title']);
        $this->assertCount(8, $shown['event']['slots']); // 2 days x 2 hours x 2 slots
        $this->assertSame([], $shown['responses']);
        $this->assertNull($shown['me']);
        $this->assertFalse(str_contains(json_encode($shown), 'hash'), 'no hashes leave the server');
    }

    public function testThePassphraseIsRequiredAndMayBeStoredHashed(): void
    {
        $this->assertSame('passphrase', self::codeOf(fn () => $this->api()->create(self::newEvent(['passphrase' => 'wrong']))));
        $this->assertSame('passphrase', self::codeOf(fn () => $this->api()->create(self::newEvent(['passphrase' => null]))));

        $hashed = $this->api(['create_passphrase' => password_hash('open sesame', PASSWORD_DEFAULT)]);
        $this->assertNull(self::codeOf(fn () => $hashed->create(self::newEvent())));

        $open = $this->api(['create_passphrase' => null]);
        $this->assertNull(self::codeOf(fn () => $open->create(self::newEvent(['passphrase' => '']))));
    }

    public function testTheHoneypotAndTheRateLimitStopBots(): void
    {
        $api = $this->api(['limits' => ['events_per_hour' => 2]]);
        $this->assertSame('rejected', self::codeOf(fn () => $api->create(self::newEvent(['website' => 'http://spam.example']))));
        $api->create(self::newEvent());
        $api->create(self::newEvent());
        $this->assertSame('rate_limited', self::codeOf(fn () => $api->create(self::newEvent())));
    }

    public function testTwoPeoplePaintAndEachGetsTheirOwnAvailabilityBack(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        [$shown] = $api->show($slug);
        $slots = $shown['event']['slots'];

        self::newBrowser();
        [$ana] = $api->signin($slug, ['name' => ' Ana ', 'password' => 'pw']);
        $this->assertSame('Ana', $ana['me']);
        $api->saveMine($slug, ['slots' => [$slots[0], $slots[1], 999]]);
        $anaCookies = $_COOKIE;

        self::newBrowser();
        $this->assertSame('signed_out', self::codeOf(fn () => $api->saveMine($slug, ['slots' => [$slots[5]]])));
        $api->signin($slug, ['name' => 'Ben']);
        $api->saveMine($slug, ['slots' => [$slots[1], $slots[2]]]);
        $api->saveMine($slug, ['slots' => [$slots[1], $slots[3]]]); // a later save replaces the earlier one

        // Ben reloads.
        [$forBen] = $api->show($slug);
        $this->assertSame('Ben', $forBen['me']);
        $this->assertSame(
            [['name' => 'Ana', 'slots' => [$slots[0], $slots[1]]], ['name' => 'Ben', 'slots' => [$slots[1], $slots[3]]]],
            $forBen['responses']
        );

        // Ana reloads in her own browser.
        $_COOKIE = $anaCookies;
        [$forAna] = $api->show($slug);
        $this->assertSame('Ana', $forAna['me']);
    }

    public function testANameWithAPasswordCanOnlyBeReopenedWithIt(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        $api->signin($slug, ['name' => 'Ana', 'password' => 'pw']);
        $api->signin($slug, ['name' => 'Ben']);

        self::newBrowser();
        $this->assertSame('signin', self::codeOf(fn () => $api->signin($slug, ['name' => 'ANA', 'password' => 'nope'])));
        $this->assertSame('signin', self::codeOf(fn () => $api->signin($slug, ['name' => 'ana'])));
        [$again] = $api->signin($slug, ['name' => 'ana', 'password' => 'pw']);
        $this->assertSame('Ana', $again['me'], 'names match case-insensitively and keep their first spelling');
        [$ben] = $api->signin($slug, ['name' => 'BEN']);
        $this->assertSame('Ben', $ben['me'], 'a name without a password can be reopened by anyone');
        $this->assertCount(2, $ben['responses']);

        $this->assertSame('name', self::codeOf(fn () => $api->signin($slug, ['name' => '  '])));
        $this->assertSame('not_found', self::codeOf(fn () => $api->signin('nosuchev', ['name' => 'Ana'])));
    }

    public function testRepeatedWrongPasswordsAreLockedOut(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        $api->signin($slug, ['name' => 'Ana', 'password' => 'pw']);
        self::newBrowser();
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame('signin', self::codeOf(fn () => $api->signin($slug, ['name' => 'Ana', 'password' => "guess$i"])));
        }
        $this->assertSame('rate_limited', self::codeOf(fn () => $api->signin($slug, ['name' => 'Ana', 'password' => 'pw'])));
    }

    public function testTheRespondentCapAndSignOut(): void
    {
        $api = $this->api(['limits' => ['max_respondents' => 2]]);
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        $api->signin($slug, ['name' => 'Ana']);
        $api->signin($slug, ['name' => 'Ben']);
        $this->assertSame('full', self::codeOf(fn () => $api->signin($slug, ['name' => 'Chidi'])));
        $this->assertNull(self::codeOf(fn () => $api->signin($slug, ['name' => 'Ana'])), 'existing people can still return');

        $api->signout($slug);
        [$shown] = $api->show($slug);
        $this->assertNull($shown['me']);
    }

    public function testPollingReportsUnchangedUntilSomethingChanges(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        [$first] = $api->show($slug);
        [$again] = $api->show($slug, $first['version']);
        $this->assertTrue($again['unchanged']);

        $api->signin($slug, ['name' => 'Ana']);
        [$after] = $api->show($slug, $first['version']);
        $this->assertFalse(isset($after['unchanged']));
        $this->assertCount(1, $after['responses']);
    }

    public function testExpiredEventsDisappear(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $this->db->exec("UPDATE events SET expires_at = '2000-01-01 00:00:00'");
        $this->assertSame('not_found', self::codeOf(fn () => $api->show($created['slug'])));
        $this->assertSame(1, (new Events($this->db))->purgeExpired());
    }

    // ----- The private admin link -----

    public function testAdminActionsNeedTheTokenFromThePrivateLink(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];

        foreach (['adminUpdate', 'adminRemove', 'adminDelete'] as $method) {
            $this->assertSame('admin', self::codeOf(fn () => $api->$method($slug, [])));
            $this->assertSame('admin', self::codeOf(fn () => $api->$method($slug, ['token' => 'wrong'])));
            $this->assertSame('admin', self::codeOf(fn () => $api->$method($slug, ['token' => ['x']])));
        }
        $this->assertSame('not_found', self::codeOf(fn () => $api->adminDelete('nosuchev', ['token' => $created['admin_token']])));
        [$shown] = $api->show($slug);
        $this->assertSame('Reading group', $shown['event']['title'], 'nothing changed');
    }

    public function testAdminCanRenameAndChangeTheTheme(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        $token = $created['admin_token'];
        [$before] = $api->show($slug);

        [$after] = $api->adminUpdate($slug, ['token' => $token, 'title' => "  Thesis \n defense ", 'theme' => 'alt']);
        $this->assertSame('Thesis defense', $after['event']['title']);
        $this->assertSame('alt', $after['event']['theme']);
        $this->assertSame($before['event']['slots'], $after['event']['slots'], 'the days are untouched');

        [$polled] = $api->show($slug, $before['version']);
        $this->assertSame('Thesis defense', $polled['event']['title'], 'open pages pick up the new title');

        [$reset] = $api->adminUpdate($slug, ['token' => $token, 'theme' => '']);
        $this->assertNull($reset['event']['theme']);
        $this->assertSame('Thesis defense', $reset['event']['title'], 'fields left out stay as they were');

        $this->assertSame('title', self::codeOf(fn () => $api->adminUpdate($slug, ['token' => $token, 'title' => ' '])));
        $this->assertSame('theme', self::codeOf(fn () => $api->adminUpdate($slug, ['token' => $token, 'theme' => 'nope'])));
    }

    public function testAdminCanAddDatesAndEveryonesTimesSurvive(): void
    {
        $api = $this->api(['limits' => ['max_days' => 7]]);
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        $token = $created['admin_token'];
        [$before] = $api->show($slug);
        $slots = $before['event']['slots'];
        $expiresBefore = $this->db->query('SELECT expires_at FROM events')->fetchColumn();

        $api->signin($slug, ['name' => 'Ana']);
        $api->saveMine($slug, ['slots' => [$slots[0], $slots[7]]]);

        // One new date, one already there, out of order.
        [$after] = $api->adminUpdate($slug, ['token' => $token, 'add_days' => ['2026-12-10', '2026-12-07']]);
        $this->assertSame(['2026-12-07', '2026-12-08', '2026-12-10'], $after['event']['days']);
        $this->assertCount(12, $after['event']['slots']);
        $this->assertSame($slots, array_slice($after['event']['slots'], 0, 8), 'existing slots keep their instants');
        $this->assertSame('2027-03-10 23:59:59', $after['event']['expires_at'], 'kept for 90 days after the new last date');
        $this->assertTrue($after['event']['expires_at'] > $expiresBefore);

        [$shown] = $api->show($slug);
        $this->assertSame([$slots[0], $slots[7]], $shown['responses'][0]['slots']);
        $this->assertFalse($shown['version'] === $before['version']);

        // People can paint the new day.
        $new = $shown['event']['slots'][8];
        $api->saveMine($slug, ['slots' => [$slots[0], $new]]);
        [$shown] = $api->show($slug);
        $this->assertSame([$slots[0], $new], $shown['responses'][0]['slots']);

        // The range cap still applies, and a bad date changes nothing.
        $this->assertSame('range', self::codeOf(fn () => $api->adminUpdate($slug, ['token' => $token, 'add_days' => ['2026-12-20']])));
        $this->assertSame('days', self::codeOf(fn () => $api->adminUpdate($slug, ['token' => $token, 'add_days' => ['2026-13-40']])));
        $this->assertSame('days', self::codeOf(fn () => $api->adminUpdate($slug, ['token' => $token, 'add_days' => 'tomorrow'])));
        [$shown] = $api->show($slug);
        $this->assertCount(3, $shown['event']['days']);
    }

    public function testAdminCanAddWeekdaysToAWeeklyEvent(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent(['mode' => 'weekdays', 'days' => [1, 3]]));
        [$after] = $api->adminUpdate($created['slug'], ['token' => $created['admin_token'], 'add_days' => [5, 1]]);
        $this->assertSame([1, 3, 5], $after['event']['days']);
        $this->assertSame('days', self::codeOf(
            fn () => $api->adminUpdate($created['slug'], ['token' => $created['admin_token'], 'add_days' => [9]])
        ));
    }

    public function testAdminCanRemoveARespondent(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        $slug = $created['slug'];
        $token = $created['admin_token'];
        [$shown] = $api->show($slug);
        $slots = $shown['event']['slots'];

        $api->signin($slug, ['name' => 'Ana', 'password' => 'pw']);
        $api->saveMine($slug, ['slots' => [$slots[0]]]);
        $anaCookies = $_COOKIE;
        self::newBrowser();
        $api->signin($slug, ['name' => 'Ben']);

        $list = $api->adminSnapshot((new Events($this->db))->find($slug))['responses'];
        $this->assertSame(['Ana', 'Ben'], array_column($list, 'name'));
        $this->assertSame([1, 0], array_column($list, 'count'));
        $this->assertSame([true, false], array_column($list, 'locked'));
        $this->assertFalse(str_contains(json_encode($list), 'hash'));

        [$after] = $api->adminRemove($slug, ['token' => $token, 'response_id' => $list[0]['id']]);
        $this->assertSame(['Ben'], array_column($after['responses'], 'name'));
        $this->assertSame('respondent', self::codeOf(fn () => $api->adminRemove($slug, ['token' => $token, 'response_id' => $list[0]['id']])));
        $this->assertSame('respondent', self::codeOf(fn () => $api->adminRemove($slug, ['token' => $token, 'response_id' => 'x'])));

        // Ana's browser is signed out, and her saves are refused.
        $_COOKIE = $anaCookies;
        [$forAna] = $api->show($slug);
        $this->assertNull($forAna['me']);
        $this->assertSame('signed_out', self::codeOf(fn () => $api->saveMine($slug, ['slots' => [$slots[1]]])));

        // The name is free again, with no password attached.
        [$again] = $api->signin($slug, ['name' => 'Ana']);
        $this->assertSame('Ana', $again['me']);
        $this->assertSame([], $again['responses'][1]['slots']);
    }

    public function testARespondentOfAnotherEventCannotBeRemoved(): void
    {
        $api = $this->api();
        [$one] = $api->create(self::newEvent());
        [$two] = $api->create(self::newEvent());
        $api->signin($two['slug'], ['name' => 'Ana']);
        $id = $api->adminSnapshot((new Events($this->db))->find($two['slug']))['responses'][0]['id'];

        $this->assertSame('respondent', self::codeOf(
            fn () => $api->adminRemove($one['slug'], ['token' => $one['admin_token'], 'response_id' => $id])
        ));
        $this->assertSame('admin', self::codeOf(
            fn () => $api->adminRemove($two['slug'], ['token' => $one['admin_token'], 'response_id' => $id])
        ));
        [$shown] = $api->show($two['slug']);
        $this->assertCount(1, $shown['responses']);
    }

    public function testAdminCanDeleteTheEventAndItsResponses(): void
    {
        $api = $this->api();
        [$created] = $api->create(self::newEvent());
        [$other] = $api->create(self::newEvent());
        $api->signin($created['slug'], ['name' => 'Ana']);
        $api->signin($other['slug'], ['name' => 'Ben']);

        $api->adminDelete($created['slug'], ['token' => $created['admin_token']]);
        $this->assertSame('not_found', self::codeOf(fn () => $api->show($created['slug'])));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM events')->fetchColumn());
        $this->assertSame(['Ben'], $this->db->query('SELECT name FROM responses')->fetchAll(\PDO::FETCH_COLUMN));
    }
}
