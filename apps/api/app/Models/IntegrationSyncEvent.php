<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncEvent extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'integration_sync_state_id',
        'system',
        'domain',
        'direction',
        'entity_type',
        'entity_id',
        'dealer_id',
        'customer_id',
        'external_ref',
        'status',
        'error',
        'payload_hash',
        'meta',
        'happened_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'integration_sync_state_id' => 'integer',
            'entity_id' => 'integer',
            'dealer_id' => 'integer',
            'customer_id' => 'integer',
            'meta' => 'array',
            'happened_at' => 'datetime',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(IntegrationSyncState::class, 'integration_sync_state_id');
    }
}
