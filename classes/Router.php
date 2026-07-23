<?php

namespace OranFry\Subsimple;

class Router
{
    public static function add(string $path, array $result): void
    {
        static::$routes[$path] = $result;
    }

    public final static function match(string $path, array $page_params = []): ?array
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
                $groups = [null]; // means no URL
                $routeparts = explode(' ', $pattern);
                $pathparts = explode(' ', $path);

                foreach ($routeparts as $routepart_index => $routepart) {
                    if ($routepart === '*') {
                        break;
                    }

                    if (
                        count($pathparts) < $routepart_index + 1
                        || !preg_match('@^' . str_replace('@', '\@', $routepart) . '$@', $pathparts[$routepart_index])
                    ) {
                        continue 2;
                    }

                    $groups[] = $pathparts[$routepart_index];
                }
            } elseif (!preg_match("@^{$pattern}$@", $path, $groups)) {
                continue;
            }

            // in cli scenario, currently all path parts match corresponding
            // route parts

            // now check for leftover path parts which break the match

            if (
                $subsimple_method === 'CLI'
                && $routepart !== '*'
                && count($pathparts) > $routepart_index + 1
            ) {
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

                $eaten = null;

                $eatPattern = match (true) {
                    isset($params['EAT_REGEX']) => $params['EAT_REGEX'],
                    isset($params['EAT']) => preg_quote($params['EAT'], '@'),
                    default => null,
                };

                if ($eatPattern) {
                    if (!preg_match('@^(' . $eatPattern . ')(.*)@', $forwardpath, $matches)) {
                        throw new Exception('Eat or eat regex does not match', 500);
                    }

                    if (count($matches) !== 3) {
                        throw new Exception('Eat regex contains capture groups', 500);
                    }

                    $eaten = $matches[1];
                    $forwardpath = $matches[2];
                }

                if (isset($params['PREPEND'])) {
                    $forwardpath = $params['PREPEND'] . ($subsimple_method === 'CLI' ? ' ' : null) . $forwardpath;
                }

                if ($subsimple_method === 'CLI') {
                    $forwardpath = implode(' ', array_filter(preg_split('/\s+/', $forwardpath)));
                } elseif (!$forwardpath) {
                    $forwardpath = '/';
                }

                $result = $params['FORWARD']::match($forwardpath, $page_params);

                if (isset($result['page_params']['REDIRECT'])) {
                    if (isset($params['PREPEND'])) {
                        if (!preg_match('@^' . preg_quote($params['PREPEND'], '@') . '(.*)@', $result['page_params']['REDIRECT'], $matches)) {
                            throw new Exception('Redirect does not start with PREPEND string', 500);
                        }

                        $result['page_params']['REDIRECT'] = $matches[1];
                    }

                    $result['page_params']['REDIRECT'] = $eaten . $result['page_params']['REDIRECT'];
                }

                return $result;
            }

            return compact('subsimple_method', 'url', 'page_params');
        }

        return null;
    }
}
