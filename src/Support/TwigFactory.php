<?php

declare(strict_types=1);

namespace App\Support;

use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigFactory
{
    /** @param array<string, mixed> $globals */
    public static function create(string $templateDir, ?string $cacheDir, bool $debug, array $globals = []): Environment
    {
        $twig = new Environment(new FilesystemLoader($templateDir), ['cache' => $cacheDir ?? false, 'debug' => $debug, 'autoescape' => 'html', 'strict_variables' => $debug]);
        if ($debug) {
            $twig->addExtension(new DebugExtension());
        }
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
