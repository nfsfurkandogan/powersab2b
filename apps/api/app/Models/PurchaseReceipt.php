<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'created_by',
        'receipt_no',
        'document_no',
        'supplier_name',
        'warehouse_code',
        'warehouse_name',
        'received_at',
        'note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dealer_id' => 'integer',
            'created_by' => 'integer',
            'received_at' => 'date',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}
