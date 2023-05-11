# UrlRewrite
[![PHP Composer](https://github.com/lazychanger/urlrewrite/actions/workflows/php.yml/badge.svg)](https://github.com/lazychanger/urlrewrite/actions/workflows/php.yml)
[![PHPUnit](https://github.com/lazychanger/urlrewrite/actions/workflows/test.yml/badge.svg)](https://github.com/lazychanger/urlrewrite/actions/workflows/test.yml)
[![Release](https://github.com/lazychanger/urlrewrite/actions/workflows/release.yml/badge.svg)](https://github.com/lazychanger/urlrewrite/actions/workflows/release.yml)

## Introduction
Easy, simple and elegant way to add http rewrite rule with `psr/http-message`.

## UrlRewriteRule

| name         | comment                                           |
|--------------|---------------------------------------------------|
| matchPath    | match path prefix or exact path                   |
| matchHost    | match request header `Host`                       |
| regMatchPath | use regexp match path                             |
| regMatchHost | use regexp match host                             |
| matchMethod  | match request method，not allowed to be used alone |

## How to Use

### Match the prefix path

```php
$request = (new UrlRewrite([
    UrlRewriteRule::matchPath('/enter')->rewriteTo('/rewrite'),
]))->rewrite($request);
```

- match: `/enter` => `/rewrite`
- match: `/enter/foo` => `/rewrite/foo`
- match: `/enter1/foo` => `/rewrite1/foo`
- nomatch: `/nomatch/foo` => `/nomatch/foo`

### Match the exact path

```php
$request = (new UrlRewrite([
    UrlRewriteRule::matchPath('/enter', true)->rewriteTo('/rewrite'),
]))->rewrite($request);
```

- match: `/enter` => `/rewrite`
- nomatch: `/enter/foo` => `/enter/foo`

### Match host

```php
$request = (new UrlRewrite([
    UrlRewriteRule::matchHost('example.com')->rewriteTo('/rewrite'),
]))->rewrite($request);
```

- match: `example.com/enter` => `example.com/rewrite`
- match: `example.com/enter/foo` => `example.com/rewrite`
- nomatch `sub.example.com/enter/foo` => `sub.example.com/enter/foo`

### Use regexp match host

```php
$request = (new UrlRewrite([
    UrlRewriteRule::regMatchHost('{subdomain:[a-z]+}.example.com')
    ->regMatchPath('/user/{id:\d+}')
    ->rewriteTo('/rewrite/{subdomain}/{id}'),
]))->rewrite($request);
```

- match: `sub.example.com/user/1` => `sub.example.com/rewrite/sub/1`
- match: `sub2.example.com/user/2` => `sub.example.com/rewrite/sub2/2`
- nomatch: `example.com/enter/foo` => `example.com/enter/foo`
- nomatch: `sub.example.com/user/foo` => `sub.example.com/user/foo`

## Q&A

### How can i got old path

**Obtain through the request header `X-Real-Path`, just `rewriteTo`, no `rewriteToFn`**

### How to use in hyperf
1. Require package and publish config
```shell
composer require lazychanger/urlrewrite
php ./bin/hyperf vendor:publish lazychanger/urlrewrite
```

2. Replace CoreMiddleware in `config/autoload/dependencies.php` or add the code to your CoreMiddleware
```php
// add config/autoload/dependencies.php config

use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use LazyChanger\UrlRewrite\Middleware\CoreMiddleware;

return [
  CoreMiddlewareInterface::class => CoreMiddleware::class
];
```

```php
// or add UrlRewrite code in app/Middleware/CoreMiddleware.php

class CoreMiddleware extends HyperfCoreMiddleware
{
    protected UrlRewrite $rewrite;

    public function __construct(ContainerInterface $container, string $serverName)
    {
        parent::__construct($container, $serverName);

        // add UrlRewrite object
        $this->rewrite = $this->container->get(UrlRewrite::class);
    }

    public function dispatch(ServerRequestInterface $request): ServerRequestInterface
    {
        return parent::dispatch($this->rewrite->rewrite($request));
    }
}
```

## Thanks
- Regexp Route Parse [nikic/fast-route](https://github.com/nikic/FastRoute)