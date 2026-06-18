<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'type',
        'source',
        'source_id',
        'qty',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'source_id')
            ->where('stock_movements.source', 'pos_sale');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_id')
            ->where('stock_movements.source', 'order');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'source_id')
            ->where('stock_movements.source', 'shipment');
    }
}
