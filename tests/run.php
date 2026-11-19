<?php
declare(strict_types=1);

// Runs the test suite without PHPUnit installed:
//
//   php tests/run.php
//
// The tests are ordinary PHPUnit test cases. With Composer available,
// `composer install && vendor/bin/phpunit` runs the same files. This script
// supplies a small stand-in for PHPUnit's TestCase so the suite also runs on a
// machine (or shared host) that has only PHP.

namespace PHPUnit\Framework {
    class AssertionFailedError extends \Exception
    {
    }

    abstract class TestCase
    {
        public static int $assertions = 0;

        protected function setUp(): void
        {
        }

        protected function tearDown(): void
        {
        }

        public function runBare(string $method): void
        {
            $this->setUp();
            try {
                $this->$method();
            } finally {
                $this->tearDown();
            }
        }

        private static function check(bool $ok, string $message, string $detail): void
        {
            self::$assertions++;
            if (!$ok) {
                throw new AssertionFailedError(($message !== '' ? $message . ': ' : '') . $detail);
            }
        }

        public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::check($expected === $actual, $message, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }

        public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::check($expected == $actual, $message, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }

        public static function assertTrue(mixed $actual, string $message = ''): void
        {
            self::check($actual === true, $message, 'expected true, got ' . var_export($actual, true));
        }

        public static function assertFalse(mixed $actual, string $message = ''): void
        {
            self::check($actual === false, $message, 'expected false, got ' . var_export($actual, true));
        }

        public static function assertNull(mixed $actual, string $message = ''): void
        {
            self::check($actual === null, $message, 'expected null, got ' . var_export($actual, true));
        }

        public static function assertNotNull(mixed $actual, string $message = ''): void
        {
            self::check($actual !== null, $message, 'expected a value, got null');
        }

        public static function assertCount(int $expected, \Countable|array $actual, string $message = ''): void
        {
            self::check(count($actual) === $expected, $message, "expected $expected items, got " . count($actual));
        }

        public static function fail(string $message = ''): never
        {
            throw new AssertionFailedError($message);
        }
    }
}

namespace {
    require __DIR__ . '/bootstrap.php';

    $failures = [];
    $tests = 0;
    foreach (glob(__DIR__ . '/*Test.php') as $file) {
        require $file;
        $class = 'Tests\\' . basename($file, '.php');
        foreach (get_class_methods($class) as $method) {
            if (!str_starts_with($method, 'test')) {
                continue;
            }
            $tests++;
            try {
                (new $class())->runBare($method);
                echo '.';
            } catch (\Throwable $e) {
                echo 'F';
                $failures[] = "$class::$method\n    " . $e->getMessage() . "\n    at " . basename($e->getFile()) . ':' . $e->getLine();
            }
        }
    }

    echo "\n\n";
    foreach ($failures as $failure) {
        echo "FAILED  $failure\n\n";
    }
    printf("%d tests, %d assertions, %d failures.\n", $tests, \PHPUnit\Framework\TestCase::$assertions, count($failures));
    exit($failures ? 1 : 0);
}
