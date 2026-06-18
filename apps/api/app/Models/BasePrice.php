<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasePrice extends Model
{
    use HasFactory;

    protected $table = 'base_prices';

    public $timestamps = false;

    const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'price_list_id',
        'product_id',
        'list_price',
        'currency',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
