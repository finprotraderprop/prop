<?php
declare(strict_types=1);

namespace App\Http;

/** Exact-match method+path routes, with `{id}` style numeric segments. */
final class Router
{
    /** @var array<int,array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    /** @return array{0:int,1:array} status and JSON body */
    public function dispatch(Request $req): array
    {
        $pathMatched = false;
        foreach ($this->routes as [$method, $pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';
            if (!preg_match($regex, $req->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($method !== $req->method) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $result = $handler($req, $params);
            return is_array($result) && isset($result[0]) && is_int($result[0]) ? $result : [200, $result];
        }
        throw $pathMatched ? new HttpError(405, 'method_not_allowed') : new HttpError(404, 'not_found');
    }
}
