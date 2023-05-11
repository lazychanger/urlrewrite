<?php
declare(strict_types=1);


namespace LazyChanger\UrlRewrite;


class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                UrlRewrite::class => UrlRewriteFactory::class
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for urlrewrite component.',
                    'source' => __DIR__ . '/../publish/rewrite.php',
                    'destination' => BASE_PATH . '/config/autoload/rewrite.php',
                ],
            ],
        ];
    }
}