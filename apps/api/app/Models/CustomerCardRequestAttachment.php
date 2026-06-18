<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCardRequestAttachment extends Model
{
    use HasFactory;

    public const TYPE_PHOTO = 'photo';

    public const TYPE_TAX_PLATE = 'tax_plate';

    public const TYPE_TAX_CERTIFICATE = 'tax_certificate';

    public const TYPE_TRADE_REGISTRY = 'trade_registry';

    public const TYPE_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_card_request_id',
        'uploaded_by_user_id',
        'attachment_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_PHOTO,
            self::TYPE_TAX_PLATE,
            self::TYPE_TAX_CERTIFICATE,
            self::TYPE_TRADE_REGISTRY,
            self::TYPE_OTHER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function requiredTypesForApproval(): array
    {
        return [
            self::TYPE_TAX_PLATE,
            self::TYPE_TAX_CERTIFICATE,
            self::TYPE_TRADE_REGISTRY,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_PHOTO => 'Fotoğraf',
            self::TYPE_TAX_PLATE => 'Vergi Levhası',
            self::TYPE_TAX_CERTIFICATE => 'Vergi Belgesi',
            self::TYPE_TRADE_REGISTRY => 'Ticaret Sicil',
            self::TYPE_OTHER => 'Diğer Evrak',
        ];
    }

    public static function labelFor(string $type): string
    {
        return self::typeLabels()[$type] ?? $type;
    }

    public function customerCardRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerCardRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
