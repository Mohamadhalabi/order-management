<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'rate', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'bool',
        'is_active'  => 'bool',
        'rate'       => 'decimal:8',
    ];

    public static function default(): ?self
    {
        return static::where('is_default', true)->first()
            ?? static::where('code', 'USD')->first()
            ?? static::first();
    }

    /** For Select options: [ 'USD' => 'USD ($)', ... ] */
    public static function activeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->code => "{$c->code}" . ($c->symbol ? " ({$c->symbol})" : '')])
            ->toArray();
    }

    public static function rateFor(string $code): float
    {
        return (float) (static::where('code', $code)->value('rate') ?? 1.0);
    }

    public static function symbolFor(?string $code): ?string
    {
        if (blank($code)) {
            return '$';
        }

        return static::query()
            ->where('code', strtoupper($code))
            ->value('symbol')
            ?? match (strtoupper($code)) {
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'TRY' => '₺',
                'AED' => 'د.إ',
                'SAR' => '﷼',
                default => $code,
            };
    }


    
}
