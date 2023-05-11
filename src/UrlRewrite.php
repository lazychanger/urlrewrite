<?php

declare(strict_types=1);


namespace LazyChanger\UrlRewrite;

use Psr\Http\Message\RequestInterface;

use function count;
use function strlen;
use function substr;
use function call_user_func;

class UrlRewrite
{
    /**
     * host > path > prefix
     *
     * @var array<string, array<string, UrlRewriteRule>>
     */
    protected array $staticRules = [
        'path' => [],
        'host' => [],
    ];

    /**
     * @param array<array-key, UrlRewriteRule> $rules
     */
    public function __construct(protected array $rules)
    {
        foreach ($rules as $rule) {
            $rewriteRules = $rule->getRules();
            if (count($rewriteRules) !== 1) {
                continue;
            }

            switch ($rewriteRules[0]['type']) {
                case UrlRewriteRule::MATCH_PATH:
                    if ($rewriteRules[0]['arguments']['exact']) {
                        $this->staticRules['path'][$rewriteRules[0]['arguments']['prefix']] = $rule;
                    }
                    break;
                case UrlRewriteRule::MATCH_HOST:
                    $this->staticRules['host'][$rewriteRules[0]['arguments']['host']] = $rule;
                    break;
            }
        }

    }

    public function rewrite(RequestInterface $request, array &$variables = null): RequestInterface
    {
        $host = $request->getHeader('host')[0] ?? '';
        if (!empty($host) && !empty($rule = ($this->staticRules['host'][$host] ?? null))) {
            return ($rule->getRewriteHandler())($request);
        }

        $path = $request->getUri()->getPath();
        if (!empty($rule = ($this->staticRules['path'][$path] ?? null))) {
            return ($rule->getRewriteHandler())($request);
        }

        foreach ($this->rules as $rule) {
            if ($result = $rule->match($request)) {
                if ($result[0] === UrlRewriteRule::RESULT_NO_MATCH) {
                    continue;
                }

                if (isset($result[1]['prefix'])) {
                    $request = $request->withUri(
                        $request->getUri()
                            ->withPath(substr($request->getUri()->getPath(), strlen($result[1]['prefix'])))
                    );
                }

                $variables = $result[1] ?? [];
                return call_user_func($rule->getRewriteHandler(), $request, $result[1]);
            }
        }

        return $request;
    }
}