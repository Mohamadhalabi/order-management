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
    protected static ?string $navigationLabel = 'Woo Senkronizasyonu';
    protected static ?string $title           = 'Woo Senkronizasyonu';
    protected static ?string $breadcrumb      = 'Senkronizasyon';
    protected static ?string $navigationGroup = 'Katalog';

    public function __construct(private WooClient $client) {}

    /** Sync products: DOES NOT TOUCH stock */
    public function syncProducts(): int
    {
        $count = 0;

        // 1. Fetch exchange rate from your custom API
        $tryRate = 1; // fallback
        try {
            $response = @file_get_contents('https://www.aanahtar.com.tr/wp-json/custom/v1/exchange-rates');
            if ($response !== false) {
                $rates = json_decode($response, true);
                if (!empty($rates['TRY']['rate'])) {
                    $tryRate = (float) $rates['TRY']['rate'];
                }
            }
        } catch (\Exception $e) {
            \Log::error('WooSync exchange rate fetch failed: ' . $e->getMessage());
        }

        \DB::transaction(function () use (&$count, $tryRate) {
            foreach ($this->client->pagedGet('/products') as $p) {
                $wcId = (int) ($p['id'] ?? 0);
                $rawSku = trim((string) ($p['sku'] ?? ''));
                $sku    = $rawSku !== '' ? $rawSku : "WC-{$wcId}";

                // Load product by wc_id OR SKU
                $product = \App\Models\Product::where('wc_id', $wcId)->first();
                if (!$product) {
                    $product = \App\Models\Product::firstOrNew(['sku' => $sku]);
                }

                if (empty($product->sku)) {
                    $product->sku = $sku;
                }

                $product->wc_id = $wcId;
                $product->name  = (string) ($p['name'] ?? $product->name ?? '');

                // Prices from WooCommerce (USD) → Convert to TRY
                $regUSD  = (float) ($p['regular_price'] ?? 0);
                $saleUSD = (float) ($p['sale_price'] ?? 0);
                $priceUSD = (float) ($p['price'] ?? $regUSD);

                $reg  = $regUSD * $tryRate;
                $sale = $saleUSD * $tryRate;
                $price = $priceUSD * $tryRate;

                // Apply pricing logic
                if ($sale > 0) {
                    $product->sale_price = $sale;
                    $product->price = $price > 0 ? $price : $reg;
                } else {
                    if ($product->price <= 0 && $reg > 0) {
                        $product->price = $reg;
                    } else {
                        $product->price = $price;
                    }
                }

                // Product image
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