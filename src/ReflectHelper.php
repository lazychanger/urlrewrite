<?php

declare(strict_types=1);


namespace LazyChanger\UrlRewrite;


use ReflectionClass;
use ReflectionException;

class ReflectHelper
{
    /**
     * @param string|object $class
     * @return array
     * @throws ReflectionException
     */
    public static function getMethods(string|object $class): array
    {
        $class = new ReflectionClass($class);

        preg_match_all('/@method([ a-z]+)? ([a-zA-Z0-9]+)\((.*)\)\n/', $class->getDocComment(), $matches);

        $methods = [];

        foreach ($matches[2] as $key => $method) {
            $argumentsStr = $matches[3][$key];
            $methods[$method] = [];

            foreach (explode(',', $argumentsStr) as $col) {
                if (empty($col)) {
                    continue;
                }

                [$type, $argument] = explode('$', $col);

                $defaultValue = null;

                $hasDefaultValue = strpos($argument, '=');
                if ($hasDefaultValue !== false) {
                    $defaultValue = substr($argument, $hasDefaultValue + 1);
                    $argument = substr($argument, 0, $hasDefaultValue);
                }


                $methods[$method][] = [
                    'type' => trim($type),
                    'name' => trim($argument),
                    'defaultValue' => self::parseMethodDefaultValue($defaultValue),
                    'noDefaultValue' => is_null($defaultValue),
                ];

            }
        }

        return $methods;
    }

    protected static function parseMethodDefaultValue($value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $value = trim($value);

        switch ($value) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        if (str_starts_with($value, '\'') || str_starts_with($value, '"')) {
            return trim($value, '\'"');
        }
        return intval($value);
    }
}