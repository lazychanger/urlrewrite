<?php
declare(strict_types=1);


namespace LazyChangerTest\UrlRewrite\Cases;


use Mockery;
use Psr\Http\Message\UriInterface;
use Stringable;

class IncompleteUri implements Stringable
{
    public static function create(string $path = '/', string $query = ''): UriInterface
    {
        return Mockery::mock(UriInterface::class, new IncompleteUri($path, $query));
    }


    public function __construct(protected string $path = '/', protected string $query = '')
    {
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return UriInterface
     */
    public function withPath(string $path)
    {
        $this->path = $path;
        return Mockery::mock(UriInterface::class, $this);
    }

    /**
     * @param string $query
     * @return UriInterface
     */
    public function withQuery(string $query)
    {
        $this->query = $query;
        return Mockery::mock(UriInterface::class, $this);
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    public function __toString()
    {
        return $this->path . (empty($this->query) ? '' : ('?' . $this->query));
    }


}