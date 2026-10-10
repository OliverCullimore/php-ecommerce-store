<?php

declare(strict_types=1);

// Minimal stand-in for PHPUnit's TestCase so the pure-domain suite can run
// where Composer/PHPUnit can't be installed. Real runs use PHPUnit 11.
namespace PHPUnit\Framework;

if (!class_exists(TestCase::class)) {
    class AssertionFailedError extends \Exception
    {
    }

    abstract class TestCase
    {
        private ?string $expectedException = null;

        public function expectException(string $class): void
        {
            $this->expectedException = $class;
        }

        public function expectedException(): ?string
        {
            return $this->expectedException;
        }

        public static function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
        {
            if ($expected !== $actual) {
                throw new AssertionFailedError($msg . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
            }
        }

        public static function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void
        {
            if ($expected != $actual) {
                throw new AssertionFailedError($msg . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
            }
        }

        public static function assertEqualsWithDelta(float $expected, float $actual, float $delta, string $msg = ''): void
        {
            if (abs($expected - $actual) > $delta) {
                throw new AssertionFailedError($msg . " expected $expected got $actual");
            }
        }

        public static function assertTrue(mixed $v, string $msg = ''): void
        {
            self::assertSame(true, $v, $msg);
        }

        public static function assertFalse(mixed $v, string $msg = ''): void
        {
            self::assertSame(false, $v, $msg);
        }

        public static function assertCount(int $n, \Countable|array $c, string $msg = ''): void
        {
            self::assertSame($n, count($c), $msg);
        }

        public static function assertStringContainsString(string $needle, string $hay, string $msg = ''): void
        {
            if (!str_contains($hay, $needle)) {
                throw new AssertionFailedError($msg . " '$needle' not found in '$hay'");
            }
        }
    }
}
