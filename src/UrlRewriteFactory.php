<?php

declare(strict_types=1);


namespace LazyChanger\UrlRewrite;


use Hyperf\Contract\ConfigInterface;
use LazyChanger\UrlRewrite\Exception\UrlRewriteException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class UrlRewriteFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws UrlRewriteException
     */
    public function __invoke(ContainerInterface $container): UrlRewrite
    {
        $config = $container->get(ConfigInterface::class)->get('rewrite', []);

        foreach ($config['rules'] ?? [] as $value) {
            if (!($value instanceof UrlRewriteRule)) {
                throw new UrlRewriteException(sprintf('Rules must be %s[], got %s', UrlRewriteRule::class, $value));
            }
        }

        return new UrlRewrite($config['rules'] ?? []);
    }
}