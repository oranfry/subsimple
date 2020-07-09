<?php
class Router
{
    protected static $routes = [];

    public static function match($path)
    {
        foreach (static::$routes as $route => $params) {
            if (!preg_match('/^(GET|POST|DELETE)\s+(\S+)$/', $route, $groups) && !preg_match('/^(CLI)\s+(.*)$/', $route, $groups)) {
                error_response("Invalid route: {$route}");
            }

            list(, $method, $pattern) = $groups;

            if ($method != ($_SERVER['REQUEST_METHOD'] ?? 'CLI')) {
                continue;
            }

            if ($method == 'CLI') {
                $routeparts = explode(' ', $pattern);
                $pathparts = explode(' ', $path);

                if (count($routeparts) != count($pathparts)) {
                    continue;
                }

                foreach ($routeparts as $i => $routepart) {
                    if (!preg_match('@' . str_replace('@', '\@', $routepart) . '@', $pathparts[$i])) {
                        continue 2;
                    }
                }

                $groups = array_merge(['CLI'], $pathparts);
            } elseif (!preg_match("@^{$pattern}$@", $path, $groups)) {
                continue;
            }

            array_shift($groups);

            $page_params = [];

            foreach ($groups as $i => $group) {
                if (!isset($params[$i])) {
                    error_response('Routing error', 500);
                }

                $page_params[$params[$i]] = $group;
            }

            foreach ($params as $key => $value) {
                if (!is_int($key)) {
                    $page_params[$key] = $value;
                }
            }

            define('PAGE_PARAMS', $page_params);

            foreach ($page_params as $key => $value) {
                define($key, $value);
            }

            return true;
        }

        return false;
    }
}
