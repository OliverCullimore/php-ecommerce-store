<?php

declare(strict_types=1);

namespace App\Support;

use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use DateTimeInterface;
use DateTimeImmutable;
use Exception;

final class TwigFactory
{
    /** @param array<string, mixed> $globals */
    public static function create(string $templateDir, ?string $cacheDir, bool $debug, array $globals = []): Environment
    {
        $twig = new Environment(new FilesystemLoader($templateDir), ['cache' => $cacheDir ?? false, 'debug' => $debug, 'autoescape' => 'html', 'strict_variables' => $debug]);
        if ($debug) {
            $twig->addExtension(new DebugExtension());
        }
        $twig->addFilter(new \Twig\TwigFilter('date', static function (DateTimeInterface|string|int|null $date): string {
            if (!$date) {
                return '';
            }

            if ($date instanceof DateTimeInterface) {
                return $date->format('d/m/Y');
            }

            if (is_numeric($date)) {
                return (new DateTimeImmutable("@$date"))->format('d/m/Y');
            }

            try {
                return (new DateTimeImmutable($date))->format('d/m/Y');
            } catch (Exception) {
                return ''; // Or handle fallback/invalid strings gracefully
            }
        }));
        $twig->addFilter(new \Twig\TwigFilter('datetime', static function (DateTimeInterface|string|int|null $datetime): string {
            if (!$datetime) {
                return '';
            }

            if ($datetime instanceof DateTimeInterface) {
                return $datetime->format('d/m/Y H:i:s');
            }

            if (is_numeric($datetime)) {
                return (new DateTimeImmutable("@$datetime"))->format('d/m/Y H:i:s');
            }

            try {
                return (new DateTimeImmutable($datetime))->format('d/m/Y H:i:s');
            } catch (Exception) {
                return ''; // Or handle fallback/invalid strings gracefully
            }
        }));
        $twig->addFilter(new TwigFilter('money', static fn (int|float|string|null $p): string => Money::format((int) $p)));
        $twig->addFilter(new TwigFilter('gbp', static fn (int|float|string|null $v): string => Money::format(Money::pence($v ?? 0))));
        $twig->addFilter(new TwigFilter('qty', static fn (int|float|string|null $v): string => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0'));
        $twig->addFilter(new TwigFilter('repeat', static fn (string $s, int $n): string => str_repeat($s, max(0, $n))));
        $twig->addFunction(new TwigFunction('csrf_field', static fn (): string => '<input type="hidden" name="_csrf" value="' . htmlspecialchars(Csrf::token(), ENT_QUOTES) . '">', ['is_safe' => ['html']]));
        foreach ($globals as $k => $v) {
            $twig->addGlobal($k, $v);
        }

        return $twig;
    }
}
