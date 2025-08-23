<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WooClient
{
    public function __construct(
        private ?string $base = null,
        private ?string $key = null,
        private ?string $secret = null,
        private int $perPage = 100,
    ) {
        $this->base   = config('services.woo.base', env('WOO_BASE_URL'));
        $this->key    = config('services.woo.key', env('WOO_CONSUMER_KEY'));
        $this->secret = config('services.woo.secret', env('WOO_CONSUMER_SECRET'));
        $this->perPage = (int) config('services.woo.per_page', env('WOO_PER_PAGE', 100));
    }

    private function baseApi(): string
    {
        $b = rtrim($this->base ?? '', '/');
        // Allow either full base (ends with /wc/v3) or site root; normalize
        if (str_contains($b, '/wp-json/')) {
            return $b;
        }
        return $b . '/wp-json/wc/v3';
    }

    private function client()
    {
        // Prefer query-string auth (works when Basic Auth is blocked)
        return Http::acceptJson()
            ->timeout(30)
            ->retry(3, 1000)
            ->withOptions([
                'query' => [
                    'consumer_key'    => $this->key,
                    'consumer_secret' => $this->secret,
                ],
            ]);
    }

    /**
     * Iterate over all pages for an endpoint. Yields each item.
     * Example: pagedGet('/products', ['status' => 'publish'])
     */
    public function pagedGet(string $endpoint, array $params = []): \Generator
    {
        $page = 1;
        do {
            $resp = $this->client()->get($this->baseApi() . $endpoint, array_merge($params, [
                'per_page' => $this->perPage,
                'page'     => $page,
            ]));

            try {
                $resp->throw();
            } catch (RequestException $e) {
                // Log useful diagnostics then rethrow
                logger()->error('Woo API error', [
                    'endpoint' => $endpoint,
                    'page'     => $page,
                    'status'   => optional($e->response)->status(),
                    'body'     => optional($e->response)->body(),
                    'message'  => $e->getMessage(),
                ]);
                throw $e;
            }

            $items = $resp->json() ?? [];
            foreach ($items as $it) {
                yield $it;
            }
            $page++;
        } while (count($items) === $this->perPage);
    }
}