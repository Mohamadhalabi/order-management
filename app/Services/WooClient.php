<?php
// app/Services/WooClient.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class WooClient
{
    public function __construct(
        private string $base = '',
        private string $key = '',
        private string $secret = '',
        private int $perPage = 100,
    ) {
        $this->base = config('services.woo.base', env('WOO_BASE_URL'));
        $this->key = config('services.woo.key', env('WOO_CONSUMER_KEY'));
        $this->secret = config('services.woo.secret', env('WOO_CONSUMER_SECRET'));
        $this->perPage = (int) (config('services.woo.per_page', env('WOO_PER_PAGE', 100)));
    }

    private function client()
    {
        return Http::withBasicAuth($this->key, $this->secret)->acceptJson();
    }

    /** Generic paginator: keeps calling until no results */
    public function pagedGet(string $endpoint, array $params = []): \Generator
    {
        $page = 1;
        do {
            $response = $this->client()->get(rtrim($this->base, '/').$endpoint, array_merge($params, [
                'per_page' => $this->perPage,
                'page'     => $page,
            ]));

            $response->throw();
            $items = $response->json() ?? [];
            foreach ($items as $it) {
                yield $it;
            }
            $page++;
        } while (count($items) === $this->perPage);
    }
}