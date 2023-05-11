<?php

declare(strict_types=1);


namespace LazyChangerTest\UrlRewrite\Cases;


use Mockery;
use Psr\Container\ContainerInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createContainer(array $definitions = []): ContainerInterface
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(ContainerInterface::class)->andReturnSelf();


        $container->shouldReceive('get')->andReturnNull()->byDefault();
        $container->shouldReceive('has')->andReturnFalse()->byDefault();

        foreach ($definitions as $identifier => $definition) {
            if (is_callable($definition)) {
                $object = $definition($container);
            } else {
                $object = $definition;
            }

            $container->shouldReceive('get')
                ->with($identifier)
                ->andReturn($object);
            $container->shouldReceive('has')
                ->with($identifier)
                ->andReturnTrue();
        }

        return $container;
    }
}