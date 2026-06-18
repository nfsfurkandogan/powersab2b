<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerPriceOverride extends Model
{
    use HasFactory;

    protected $table = 'dealer_price_overrides';

    public $timestamps = false;

    const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dealer_id',
        'product_id',
        'net_price',
        'currency',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'net_price' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
