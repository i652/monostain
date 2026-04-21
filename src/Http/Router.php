<?php
declare(strict_types=1);

namespace Stain\Http;

/**
 * Простая маршрутизация: порядок регистрации важен (первое совпадение выигрывает).
 *
 * @phpstan-type Route array{methods: list<string>, pattern: string, handler: \Closure(array<int|string, string>):void}
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param string|list<string> $methods GET, POST, …
     * @param non-empty-string $pattern regex с delimiters (#…#)
     * @param \Closure(array<int|string, string>):(?bool) $handler вернуть false, если совпадение не обрабатывать (искать дальше)
     */
    public function map(string|array $methods, string $pattern, \Closure $handler): void
    {
        $list = is_array($methods) ? $methods : [$methods];
        $this->routes[] = [
            'methods' => $list,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $m)) {
                $handled = ($route['handler'])($m);
                if ($handled === false) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }
}
