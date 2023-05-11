<?php

declare(strict_types=1);


namespace LazyChanger\UrlRewrite;


use LazyChanger\UrlRewrite\Exception\BadRouteException;

/**
 * @link https://github.com/nikic/FastRoute/blob/master/src/RouteParser/Std.php
 */
class RouteParser
{
    const VARIABLE_REGEX = <<<'REGEX'
\{
    \s* ([a-zA-Z_][a-zA-Z0-9_-]*) \s*
    (?:
        : \s* ([^{}]*(?:\{(?-1)\}[^{}]*)*)
    )?
\}
REGEX;
    const DEFAULT_DISPATCH_REGEX = '[^/]+';

    public function parse($route): array
    {
        $routeWithoutClosingOptionals = rtrim($route, ']');
        $numOptionals = strlen($route) - strlen($routeWithoutClosingOptionals);

        // Split on [ while skipping placeholders
        $segments = preg_split('~' . self::VARIABLE_REGEX . '(*SKIP)(*F) | \[~x', $routeWithoutClosingOptionals);
        if ($numOptionals !== count($segments) - 1) {
            // If there are any ] in the middle of the route, throw a more specific error message
            if (preg_match('~' . self::VARIABLE_REGEX . '(*SKIP)(*F) | \]~x', $routeWithoutClosingOptionals)) {
                throw new BadRouteException('Optional segments can only occur at the end of a route');
            }
            throw new BadRouteException("Number of opening '[' and closing ']' does not match");
        }

        $currentRoute = '';
        $routeDatas = [];
        foreach ($segments as $n => $segment) {
            if ($segment === '' && $n !== 0) {
                throw new BadRouteException('Empty optional part');
            }

            $currentRoute .= $segment;
            $routeDatas[] = $this->parsePlaceholders($currentRoute);
        }

        $result = [];
        foreach ($routeDatas as $routeData) {
            $regex = '';
            $variables = [];
            foreach ($routeData as $part) {
                if (is_string($part)) {
                    $regex .= preg_quote($part, '~');
                    continue;
                }

                list($varName, $regexPart) = $part;

                if (isset($variables[$varName])) {
                    throw new BadRouteException(sprintf(
                        'Cannot use the same placeholder "%s" twice', $varName
                    ));
                }

                if ($this->regexHasCapturingGroups($regexPart)) {
                    throw new BadRouteException(sprintf(
                        'Regex "%s" for parameter "%s" contains a capturing group',
                        $regexPart, $varName
                    ));
                }

                $variables[$varName] = $varName;
                $regex .= '(' . $regexPart . ')';
            }
            $result[] = ['~^' . $regex . '$~', $variables];
        }

        return $result;
    }

    /**
     * Parses a route string that does not contain optional segments.
     *
     * @param string $route
     * @return array
     */
    private function parsePlaceholders(string $route): array
    {
        if (!preg_match_all(
            '~' . self::VARIABLE_REGEX . '~x', $route, $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            return [$route];
        }

        $offset = 0;
        $routeData = [];
        foreach ($matches as $set) {
            if ($set[0][1] > $offset) {
                $routeData[] = substr($route, $offset, $set[0][1] - $offset);
            }
            $routeData[] = [
                $set[1][0],
                isset($set[2]) ? trim($set[2][0]) : self::DEFAULT_DISPATCH_REGEX
            ];
            $offset = $set[0][1] + strlen($set[0][0]);
        }

        if ($offset !== strlen($route)) {
            $routeData[] = substr($route, $offset);
        }

        return $routeData;
    }


    /**
     * @param string $regex
     * @return bool
     */
    private function regexHasCapturingGroups(string $regex): bool
    {
        if (!str_contains($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }

        // Semi-accurate detection for capturing groups
        return (bool)preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }
}