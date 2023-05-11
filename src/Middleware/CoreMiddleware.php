<?php

declare(strict_types=1);


namespace LazyChanger\UrlRewrite\Middleware;


use Hyperf\HttpServer\CoreMiddleware as HyperfCoreMiddleware;
use LazyChanger\UrlRewrite\UrlRewrite;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class CoreMiddleware extends HyperfCoreMiddleware
{
    protected UrlRewrite $rewrite;

    public function __construct(ContainerInterface $container, string $serverName)
    {
        parent::__construct($container, $serverName);

        $this->rewrite = $this->container->get(UrlRewrite::class);
    }

    public function dispatch(ServerRequestInterface $request): ServerRequestInterface
    {
        return parent::dispatch($this->rewrite->rewrite($request));
    }
}