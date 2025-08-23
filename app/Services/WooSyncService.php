<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class WooSyncService
{
    public function __construct(private WooClient $client) {}

    /** Sync products: DOES NOT TOUCH stock */
    public function syncProducts(): int
    {
        $count = 0;

        \DB::transaction(function () use (&$count) {
            foreach ($this->client->pagedGet('/products') as $p) {
                $wcId = (int) ($p['id'] ?? 0);
                $rawSku = trim((string) ($p['sku'] ?? ''));
                $sku    = $rawSku !== '' ? $rawSku : "WC-{$wcId}"; // ✅ fallback

                // 1) Prefer to load by wc_id if we’ve seen it before
                $product = \App\Models\Product::where('wc_id', $wcId)->first();

                // 2) Otherwise try by (fallback) sku
                if (! $product) {
                    $product = \App\Models\Product::firstOrNew(['sku' => $sku]);
                }

                // Ensure we have a non-empty SKU stored
                if (empty($product->sku)) {
                    $product->sku = $sku;
                }

                // Keep Excel as the source of truth for stock (do NOT touch stock here)
                $product->wc_id = $wcId;
                $product->name  = (string) ($p['name'] ?? $product->name ?? '');

                // Prices
                $product->price = (float) ($p['price'] ?? $product->price ?? 0);
                $reg  = (float) ($p['regular_price'] ?? 0);
                $sale = (float) ($p['sale_price'] ?? 0);

                if ($sale > 0) {
                    $product->sale_price = $sale;
                    if ($product->price <= 0 && $reg > 0) {
                        $product->price = $reg;
                    }
                } else {
                    // keep existing sale_price (or null)
                    if ($product->price <= 0 && $reg > 0) {
                        $product->price = $reg;
                    }
                }

                // Image: first image URL if present
                $images = $p['images'] ?? [];
                if (!empty($images) && !empty($images[0]['src'])) {
                    $product->image = $images[0]['src'];
                }

                $product->wc_synced_at = now();
                $product->save();

                $count++;
            }
        });

        return $count;
    }


    /** Sync customers into users table */
    public function syncUsers(): int
    {
        $count = 0;

        $placeholderHash = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40));

        \DB::transaction(function () use (&$count, $placeholderHash) {
            foreach ($this->client->pagedGet('/customers') as $c) {
                $wcId  = (int) ($c['id'] ?? 0);
                $email = strtolower((string) ($c['email'] ?? ''));

                if ($email === '') {
                    continue;
                }

                $user = \App\Models\User::firstOrNew(['email' => $email]);

                $user->wc_id       = $wcId;
                $user->first_name  = $c['first_name'] ?? $user->first_name;
                $user->last_name   = $c['last_name']  ?? $user->last_name;
                $user->name        = trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? '')) ?: ($user->name ?? $email);

                // Billing info from Woo
                $billing = $c['billing'] ?? [];
                $user->phone                    = $billing['phone']     ?? $user->phone;
                $user->billing_address_line1    = $billing['address_1'] ?? $user->billing_address_line1;
                $user->billing_address_line2    = $billing['address_2'] ?? $user->billing_address_line2;
                $user->billing_city             = $billing['city']      ?? $user->billing_city;
                $user->billing_state            = $billing['state']     ?? $user->billing_state;
                $user->billing_postcode         = $billing['postcode']  ?? $user->billing_postcode;
                $user->billing_country          = $billing['country']   ?? $user->billing_country;

                $user->wc_synced_at = now();

                if (! $user->exists) {
                    $user->password = $placeholderHash;
                }

                $user->save();
                $count++;
            }
        });

        return $count;
    }

}