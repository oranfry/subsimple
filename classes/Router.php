<?php

namespace subsimple;

class Router
{
    protected static $routes = [];

    public static function match($path)
    {
        foreach (static::$routes as $route => $params) {
            $method_pattern = implode('|', ['GET', 'POST', 'DELETE', 'PUT', 'HTTP']);
            $methods_pattern = '(?:' . $method_pattern . ')(?:\|(?:' . $method_pattern . '))*';
            $http_pattern = '/^(' . $methods_pattern . ')\s+(\S+)$/';
            $cli_pattern = '/^(CLI)\s+(.*)$/';

            // validate route

            if (!preg_match($http_pattern, $route, $groups) &&
                !preg_match($cli_pattern, $route, $groups)
            ) {
                error_response("Invalid route: {$route}");
            }

            list(, $method_list, $pattern) = $groups;

            $route_methods = explode('|', $method_list);

            // check that method matches

            if (!in_array(@$_SERVER['REQUEST_METHOD'] ?? 'CLI', $route_methods) &&
                (!in_array('HTTP', $route_methods) || !@$_SERVER['REQUEST_METHOD'])
            ) {
                continue;
            }

            // check that pattern matches

            if (count($route_methods) == 1 && reset($route_methods) == 'CLI') {
                if ($pattern == '*') {
                    $groups = ['CLI'];
                } else {
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
                }
            } elseif (!preg_match("@^{$pattern}$@", $path, $groups)) {
                continue;
            }

            // we have found a match

            array_shift($groups);

            if (isset($params['FORWARD'])) {
                $forwardpath = $path;

                if (isset($params['EAT'])) {
                    $forwardpath = preg_replace('@' . $params['EAT'] . '@', @$params['PREPEND'] ?? '', $path) ?: '/';
                }

                return $params['FORWARD']::match($forwardpath);
            }

            $page_params = [];

            foreach ($groups as $i => $group) {
                if (!array_key_exists($i, $params)) {
                    error_response('Routing error: please provide URL argument name', 500);
                }

                if ($params[$i]) {
                    $page_params[$params[$i]] = $group;
                }
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
