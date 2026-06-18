<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSyncState extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'system',
        'domain',
        'direction',
        'entity_type',
        'entity_id',
        'dealer_id',
        'customer_id',
        'external_ref',
        'status',
        'last_error',
        'payload_hash',
        'last_synced_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dealer_id' => 'integer',
            'customer_id' => 'integer',
            'entity_id' => 'integer',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationSyncEvent::class);
    }
}
