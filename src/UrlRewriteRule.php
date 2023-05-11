<?php

declare(strict_types=1);


namespace LazyChanger\UrlRewrite;


use Closure;
use LazyChanger\UrlRewrite\Exception\BadRouteException;
use LazyChanger\UrlRewrite\Exception\UrlRewriteException;
use Psr\Http\Message\RequestInterface;

use function strtoupper;
use function array_merge;
use function count;
use function preg_match;
use function ltrim;
use function call_user_func_array;
use function str_replace;
use function array_keys;
use function sprintf;


/**
 * @method static static matchPath(string $prefix, bool $exact = false)
 * @method static static regMatchPath(string $reg)
 * @method static static matchHost(string $host)
 * @method static static regMatchHost(string $reg)
 * @method static static matchMethod(string $method)
 */
class UrlRewriteRule
{
    const RESULT_MATCH = 1;
    const RESULT_NO_MATCH = 0;

    const MATCH_PATH = 0;
    const REG_MATCH_PATH = 1;
    const MATCH_HOST = 2;
    const REG_MATCH_HOST = 3;
    const MATCH_METHOD = 4;

    const X_REAL_PATH = 'x-real-path';

    /**
     * @var array<string, array>|null
     */
    protected static array|null $staticMethodArgumentNames = null;

    private array $rules = [];

    /**
     * @var callable(RequestInterface):RequestInterface|Closure(RequestInterface):RequestInterface|null
     */
    private $rewriteHandler = null;

    private RouteParser $parser;

    public function __construct()
    {
        $this->parser = new RouteParser();
    }

    /**
     * @param string $path
     * @return $this
     */
    public function rewriteTo(string $path): self
    {
        $path = '/' . ltrim($path, '/');

        $this->rewriteToFn(function (RequestInterface $request, array $variables = []) use (
            $path,
        ): RequestInterface {

            $path = str_replace(array_map(function ($key) {
                return sprintf('{%s}', $key);
            }, array_keys($variables)), $variables, $path);
            $uri = $request->getUri();

            if (!empty($variables['prefix'])) {
                $uri = $uri->withPath($path . $uri->getPath());
            } else {
                $uri = $uri->withPath($path);
            }

            return $request->withHeader(self::X_REAL_PATH, $request->getUri()->getPath())->withUri($uri);
        });

        return $this;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function rewriteToFn(callable $callback): self
    {
        $this->rewriteHandler = $callback;
        return $this;
    }


    /**
     * @throws BadRouteException
     */
    public function setRule(int $matchType, array $arguments = []): self
    {
        switch ($matchType) {
            case self::REG_MATCH_HOST:
            case self::REG_MATCH_PATH:
                if (!empty($arguments['reg'])) {
                    $arguments = $this->parser->parse($arguments['reg']);
                }
                break;
        }

        $this->rules[] = ['type' => $matchType, 'arguments' => $arguments];
        return $this;
    }


    public static function __callStatic(string $name, array $arguments)
    {
        return call_user_func_array([new static(), $name], $arguments);
    }

    public function __call(string $name, array $arguments)
    {
        if (!isset(self::$staticMethodArgumentNames)) {
            self::initStaticArgumentNames();
        }

        if (empty(self::$staticMethodArgumentNames[$name])) {
            throw new UrlRewriteException(sprintf('Not exists static method %s', $name));
        }

        $type = match ($name) {
            'matchPath' => self::MATCH_PATH,
            'regMatchPath' => self::REG_MATCH_PATH,
            'matchHost' => self::MATCH_HOST,
            'regMatchHost' => self::REG_MATCH_HOST,
            'matchMethod' => self::MATCH_METHOD,
            default => null
        };

        if (!isset($type)) {
            throw new UrlRewriteException(sprintf('Unsupported match type %s', $name));
        }

        return $this->setRule($type, self::formatArguments($name, $arguments));
    }

    /**
     * read static methods with self-class document and parse them into self::$staticMethodArgumentNames
     *
     * @return void
     * @throws \ReflectionException
     */
    protected static function initStaticArgumentNames(): void
    {
        self::$staticMethodArgumentNames = ReflectHelper::getMethods(self::class);
    }

    /**
     *
     *
     * @param string $method
     * @param array $arguments
     * @return array
     */
    protected static function formatArguments(string $method, array $arguments): array
    {
        $properties = self::$staticMethodArgumentNames[$method] ?? [];
        if (empty($properties)) {
            return [];
        }

        $newArguments = [];
        foreach ($properties as $key => $property) {
            $newArguments[$property['name']] = $arguments[$key] ?? ($property['defaultValue'] ?? null);
        }

        return $newArguments;
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return callable
     */
    public function getRewriteHandler(): callable
    {
        return $this->rewriteHandler ?: function (RequestInterface $request): RequestInterface {
            return $request;
        };
    }

    public function match(RequestInterface $request): array
    {
        if (empty($this->rules)) {
            return [self::RESULT_NO_MATCH];
        }

        if (count($this->rules) === 1 && $this->rules[0]['type'] === self::MATCH_METHOD) {
            return [self::RESULT_NO_MATCH];
        }

        $variables = [];

        foreach ($this->rules as $rule) {
            $match = $this->matchRule($rule, $request);
            if ($match[0] === self::RESULT_NO_MATCH) {
                return [self::RESULT_NO_MATCH];
            }
            $variables = array_merge($variables, $match[1] ?? []);
        }

        return [self::RESULT_MATCH, $variables];
    }

    protected function matchRule(array $rule, RequestInterface $request): array
    {
        ['type' => $type, 'arguments' => $arguments] = $rule;

        switch ($type) {
            case self::MATCH_PATH:
                if ($arguments['exact']) {
                    return [$this->b2m($request->getUri()->getPath() === $arguments['prefix'])];
                }
                return [
                    $this->b2m(str_starts_with($request->getUri()->getPath(), $arguments['prefix'])),
                    [
                        'prefix' => $arguments['prefix']
                    ]
                ];
            case self::MATCH_HOST:
                return [$this->b2m(($request->getHeader('Host')[0] ?? '') === $arguments['host'])];
            case self::MATCH_METHOD:
                return [$this->b2m(strtoupper($request->getMethod()) === strtoupper($arguments['method']))];
            case self::REG_MATCH_PATH:
                $subject = $request->getUri()->getPath();
                break;
            case self::REG_MATCH_HOST:
                $subject = $request->getHeader('Host')[0] ?? '';
                break;
        }

        if (!empty($subject)) {
            $vars = [];
            foreach ($arguments as $expr) {
                [$reg, $variables] = $expr;
                if (empty($reg) || !preg_match($reg, $subject, $matches)) {
                    return [self::RESULT_NO_MATCH];
                }
                $i = 0;
                foreach ($variables as $varName) {
                    $vars[$varName] = $matches[++$i];
                }
            }

            return [self::RESULT_MATCH, $vars];
        }

        return [self::RESULT_NO_MATCH];
    }

    protected function b2m(bool $condition): int
    {
        return $condition ? self::RESULT_MATCH : self::RESULT_NO_MATCH;
    }
}