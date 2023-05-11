<?php

declare(strict_types=1);


namespace LazyChangerTest\UrlRewrite\Cases;


use LazyChanger\UrlRewrite\UrlRewrite;
use LazyChanger\UrlRewrite\UrlRewriteRule;
use Psr\Http\Message\RequestInterface;

use function parse_str;
use function is_array;
use function is_callable;

class UrlRewriteTest extends TestCase
{
    protected array $exampleRules = [];

    protected function getExampleRules(): array
    {
        return [
            [
                'name' => 'matchPath',
                'rule' => UrlRewriteRule::matchPath('/matchPath')->rewriteTo('/rewrite/matchPath'),
                'got' => [
                    [
                        '/matchPath',
                    ],
                    [
                        '/matchPath/no-exact',
                    ],
                    [
                        '/nomatch',
                        false
                    ]
                ],
                'want' => function (RequestInterface $request): bool {
                    return str_starts_with($request->getUri()->getPath(), '/rewrite/matchPath');
                },
            ],
            [
                'name' => 'matchPath-exact',
                'rule' => UrlRewriteRule::matchPath('/matchPath/exact', true)->rewriteTo('/rewrite/matchPath/exact'),
                'got' => [
                    [
                        '/matchPath/exact'
                    ],
                    [
                        '/nomatchPath/exact',
                        false
                    ]
                ],
                'want' => '/rewrite/matchPath/exact',
            ],
            [
                'name' => 'regMatchPath',
                'rule' => UrlRewriteRule::regMatchPath('/regMatchPath/{id:[0-9]+}')->rewriteTo('/rewrite/regMatchPath/{id}'),
                'want' => function (RequestInterface $request, array $arguments): bool {
                    $id = $arguments['id'] ?? 0;
                    return (string)$request->getUri() === sprintf('/rewrite/regMatchPath/%d',
                            $id) && $id == 1;
                },
                'got' => [
                    [
                        '/regMatchPath/1',
                    ],
                    [
                        '/regMatchPath/a',
                        false
                    ],
                    [
                        '/regMatchPath/a/1',
                        false
                    ],
                ]
            ],
            [
                'name' => 'matchHost',
                'rule' => UrlRewriteRule::matchHost('example.com')->rewriteTo('/rewrite/matchHost'),
                'want' => '/rewrite/matchHost',
                'got' => [
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example.com');
                        }
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example.com');
                        },
                        false,
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example2.com');
                        },
                        false,
                    ]
                ]
            ],
            [
                'name' => 'regMatchHost',
                'rule' => UrlRewriteRule::regMatchHost('{subdomain:[a-zA-Z0-9]+}.example1.com')->rewriteTo('/rewrite/regMatchHost/{subdomain}'),
                'got' => [
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example1.com');
                        },
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example1.com');
                        },
                        false,
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example2.com');
                        },
                        false,
                    ],
                ],
                'want' => function (RequestInterface $request, array $arguments): bool {
                    $subdomain = $arguments['subdomain'] ?? '';
                    return (string)$request->getUri() === sprintf('/rewrite/regMatchHost/%s', $subdomain);
                },
            ],
            [
                'name' => 'regMatchHost2',
                'rule' => UrlRewriteRule::regMatchHost('{subdomain:[a-zA-Z0-9]+}.example2.com')->rewriteToFn(function (
                    RequestInterface $request,
                    array $arguments,
                ): RequestInterface {

                    $request = $request->withUri($request->getUri()->withPath('/rewrite/regMatchHost'));

                    parse_str($request->getUri()->getQuery(), $query);
                    $query = !empty($query) ? $query : [];
                    $query['subdomain'] = $arguments['subdomain'] ?? '';

                    return $request->withUri($request->getUri()->withQuery(http_build_query($query)));
                }),
                'got' => [
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example2.com');
                        },
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example2.com');
                        },
                        false,
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example3.com');
                        },
                        false,
                    ],
                ],
                'want' => function (RequestInterface $request, array $arguments): bool {
                    $subdomain = $arguments['subdomain'] ?? '';

                    parse_str($request->getUri()->getQuery(), $queryArr);

                    return ($queryArr['subdomain'] ?? '') === $subdomain;
                },
            ],
            [
                'name' => 'matchPathAndHost',
                'rule' => UrlRewriteRule::matchHost('example3.com')->matchPath('/matchPath')->rewriteTo('/rewrite/matchPathAndHost'),
                'got' => [
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example3.com')->withUri(
                                $request->getUri()->withPath('/matchPath/foo')
                            );
                        }
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example3.com')->withUri(
                                $request->getUri()->withPath('/matchPathAndHost')
                            );
                        },
                        false
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example3.com')->withUri(
                                $request->getUri()->withPath('/nomatchPath')
                            );
                        },
                        false
                    ],
                ],
                'want' => function (RequestInterface $request): bool {
                    return $request->getHeaderLine('host') === 'example3.com' && str_starts_with($request->getUri()->getPath(),
                            '/rewrite/matchPathAndHost');
                }
            ],
            [
                'name' => 'regMatchPathAndHost',
                'rule' => UrlRewriteRule::regMatchHost('{subdomain:[a-zA-Z0-9]+}.example4.com')->regMatchPath('/matchPath/{id:[0-9]+}')->rewriteToFn(function (
                    RequestInterface $request,
                    array $arguments,
                ): RequestInterface {
                    $id = $arguments['id'] ?? 0;
                    $subdomain = $arguments['subdomain'] ?? '';

                    parse_str($request->getUri()->getQuery(), $queryArr);

                    $queryArr['subdomain'] = $subdomain;
                    $queryArr['id'] = $id;

                    return $request->withUri($request->getUri()->withPath('/rewrite/regMatchPathAndHost')->withQuery(http_build_query($queryArr)));
                }),
                'got' => [
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example4.com')->withUri(
                                $request->getUri()->withPath('/matchPath/1')
                            );
                        },
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example4.com')->withUri(
                                $request->getUri()->withPath('/matchPath/1')
                            );
                        },
                        false
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example4.com')->withUri(
                                $request->getUri()->withPath('/matchPath/a')
                            );
                        },
                        false
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example4.com')->withUri(
                                $request->getUri()->withPath('/matchPath/a')
                            );
                        },
                        false
                    ]
                ],
                'want' => function (RequestInterface $request, array $arguments): bool {
                    $id = $arguments['id'] ?? 0;
                    $subdomain = $arguments['subdomain'] ?? '';
                    parse_str($request->getUri()->getQuery(), $queryArr);

                    return ($queryArr['subdomain'] ?? '') === $subdomain && ($queryArr['id'] ?? 0) == $id;
                }
            ],
            [
                'name' => 'matchMethodAndHost',
                'rule' => UrlRewriteRule::matchMethod('HEAD')->regMatchHost('{subdomain:[0-9a-zA-Z]+}.example7.com')->rewriteTo('/rewrite/matchMethodAndHost/{subdomain}'),
                'got' => [
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'subdomain.example7.com')
                                ->withMethod('HEAD')
                                ->withUri(IncompleteUri::create('/matchMethodAndHost'));
                        }
                    ],
                    [
                        function (RequestInterface $request): RequestInterface {
                            return $request->withHeader('Host', 'example7.com')
                                ->withMethod('POST')
                                ->withUri(IncompleteUri::create('/matchMethodAndHost'));
                        },
                        false
                    ]
                ],
                'want' => function (RequestInterface $request, array $arguments): bool {
                    $subdomain = $arguments['subdomain'] ?? '';
                    return (string)$request->getUri() === '/rewrite/matchMethodAndHost/' . $subdomain;
                },
            ]
        ];
    }

    public function testRewrite()
    {
        foreach ($this->getExampleRules() as $rule) {

            ['name' => $name, 'got' => $got, 'want' => $want, 'rule' => $urlRewriteRule] = $rule;

            if (!is_array($got)) {
                $got = [$got];
            }

            $this->testRewriteCase($urlRewriteRule, $name, $got, $want);
        }

    }

    protected function testRewriteCase(UrlRewriteRule $rule, string $name, array $gots, $want): void
    {
        foreach ($gots as $got) {
            $rewrite = new UrlRewrite([$rule]);
            if (count($got) === 1) {
                $got[] = true;
            }

            [$got, $value] = $got;

            $req = IncompleteRequest::create();
            if (is_callable($got)) {
                $req = call_user_func($got, $req);
            } else {
                $req = $req->withUri($req->getUri()->withPath($got));
            }

            $resultReq = $rewrite->rewrite($req, $variables);

            $this->setName($name);
            if (is_callable($want)) {
                $result = call_user_func($want, $resultReq, $variables ?? []);
                $message = '';
            } else {
                $result = $resultReq->getUri()->getPath() === $want;
                $message = sprintf('; want: %s', $want);
            }

            $this->assertEquals($result, $value,
                sprintf('%s: want %s, but got %s; inturi: %s, outuri: %s', $name, $this->b2s($value),
                    $this->b2s($result),
                    $req->getUri(),
                    $resultReq->getUri()) . $message);

            unset($req, $resultReq);
        }
    }

    protected function b2s(bool $ok): string
    {
        return $ok ? 'true' : 'false';
    }
}