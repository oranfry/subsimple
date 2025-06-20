<?php

namespace subsimple;

class Router
{
    public static function add(string $path, array $result): void
    {
        static::$routes[$path] = $result;
    }

    public final static function match(string $path, array $page_params = []): bool
    {
        $method_pattern = implode('|', ['GET', 'POST', 'DELETE', 'PUT', 'HTTP']);
        $methods_pattern = '(?:' . $method_pattern . ')(?:\|(?:' . $method_pattern . '))*';
        $http_pattern = '/^(' . $methods_pattern . ')\s+(\S+)$/';
        $cli_pattern = '/^(CLI)\s+(.*)$/';

        foreach (static::$routes ?? [] as $route => $params) {
            // validate route

            if (
                !preg_match($http_pattern, $route, $groups)
                && !preg_match($cli_pattern, $route, $groups)
            ) {
                throw new Exception("Invalid route: {$route}");
            }

            list(, $method_list, $pattern) = $groups;

            $route_methods = explode('|', $method_list);

            // check that method matches

            if (in_array($_method = @$_SERVER['REQUEST_METHOD'] ?? 'CLI', $route_methods)) {
                $subsimple_method = $_method;
            } elseif (@$_SERVER['REQUEST_METHOD'] && in_array('HTTP', $route_methods)) {
                $subsimple_method = 'HTTP';
            } else {
                continue; // method does not match
            }

            // check that pattern matches

            if ($subsimple_method === 'CLI') {
                if ($pattern !== '*') {
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

                    $groups = [null, ...$pathparts];
                }
            } elseif (!preg_match("@^{$pattern}$@", $path, $groups)) {
                continue;
            }

            // we have found a match

            $url = array_shift($groups);

            foreach ($groups as $i => $group) {
                if (!array_key_exists($i, $params)) {
                    throw new Exception('Routing error: please provide URL argument name', 500);
                }

                if ($params[$i]) {
                    $page_params[$params[$i]] = $group;
                }

                unset($params[$i]);
            }

            foreach ($params as $key => $value) {
                if (is_int($key)) {
                    $page_params[$params[$key]] = ''; // optional param which was not matched
                } else {
                    $page_params[$key] = $value;
                }
            }

            if (isset($params['FORWARD'])) {
                $forwardpath = $path;

                if (isset($params['EAT_REGEX'])) {
                    $forwardpath = preg_replace('@^' . $params['EAT_REGEX'] . '@', '', $forwardpath);
                } elseif (isset($params['EAT'])) {
                    $forwardpath = preg_replace('@^' . preg_quote($params['EAT'], '@') . '@', '', $forwardpath);
                }

                if (isset($params['PREPEND'])) {
                    $forwardpath = $params['PREPEND'] . $forwardpath;
                }

                if (!$forwardpath) {
                    $forwardpath = '/';
                }

                return $params['FORWARD']::match($forwardpath, $page_params);
            }

            define('SUBSIMPLE_METHOD', $subsimple_method);
            define('SUBSIMPLE_URL', $url);
            define('PAGE_PARAMS', $page_params);

            foreach ($page_params as $key => $value) {
                define($key, $value);
            }

            return true;
        }

        return false;
    }
}
