<?php
declare(strict_types=1);


namespace LazyChangerTest\UrlRewrite\Cases;

use Mockery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

use function is_string;

class IncompleteRequest
{
    public static function create(): RequestInterface
    {
        return Mockery::mock(RequestInterface::class, new IncompleteRequest());
    }

    /**
     * @var array<string, array<array-key, string>>
     */
    protected array $headers = [];

    protected string $method = 'GET';
    protected UriInterface $uri;

    /**
     * @param string $method
     * @return RequestInterface
     */
    public function withMethod(string $method)
    {
        $this->method = $method;
        return Mockery::mock(RequestInterface::class, $this);
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function withHeader(string $name, $header)
    {
        $this->headers[strtolower($name)] = is_string($header) ? [$header] : $header;
        return Mockery::mock(RequestInterface::class, $this);
    }

    public function getHeader(string $name)
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function hasHeader(string $name)
    {
        return isset($this->headers[$name]);
    }

    public function withoutHeader(string $name)
    {
        unset($this->headers[strtolower($name)]);
        return Mockery::mock(RequestInterface::class, $this);
    }

    public function getHeaderLine(string $name)
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withAddedHeader(string $name, string $header)
    {
        $name = strtolower($name);
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = [];
        }
        $this->headers[$name][] = $header;
        return Mockery::mock(RequestInterface::class, $this);
    }

    public function getUri()
    {
        if (empty($this->uri)) {
            $this->uri = IncompleteUri::create();

        }
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false)
    {
        $this->uri = $uri;
        return Mockery::mock(RequestInterface::class, $this);
    }
}