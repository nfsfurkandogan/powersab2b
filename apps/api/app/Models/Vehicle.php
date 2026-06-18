<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'make',
        'model',
        'trim',
        'engine',
        'fuel_type',
        'year_from',
        'year_to',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vehicle_products')
            ->withPivot(['position', 'fitment_note'])
            ->withTimestamps();
    }

    public function fitments(): HasMany
    {
        return $this->hasMany(VehicleProduct::class);
    }
}
