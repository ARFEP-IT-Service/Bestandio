<?php

declare(strict_types=1);

namespace Bestandio\Core;

final class Router
{
    /** @var list<array{0:string,1:string,2:array{0:class-string,1:string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, array $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    /**
     * @return array{0:array{0:class-string,1:string},1:array<string,string>}
     * @throws HttpException 404 or 405
     */
    public function match(string $method, string $path): array
    {
        $allowed = false;
        foreach ($this->routes as [$m, $pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[1-9][0-9]{0,9})', $pattern) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($m !== $method) {
                $allowed = true;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return [$handler, $params];
        }
        throw $allowed ? new HttpException(405, 'error.405') : new HttpException(404, 'error.404');
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
