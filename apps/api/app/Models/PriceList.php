<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'discount_rate',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function dealers(): HasMany
    {
        return $this->hasMany(Dealer::class);
    }

    public function basePrices(): HasMany
    {
        return $this->hasMany(BasePrice::class);
    }
}
