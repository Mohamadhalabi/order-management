<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    protected $fillable = [
        'customer_id','status','notes',
        'subtotal', 'shipping_amount', // <-- add
        'total','created_by_id',
        'shipping_name','shipping_phone','shipping_address_line1','shipping_address_line2',
        'shipping_city','shipping_state','shipping_postcode','shipping_country',
        'pdf_path',
    ];
    protected $casts = [
        'subtotal'        => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total'           => 'decimal:2',
    ];


    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    // Convenience accessor
    public function getFormattedTotalAttribute(): string
    {
        return number_format((float) $this->total, 2, '.', '');
    }
    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null;
    }

}