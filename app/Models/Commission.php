<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Commission extends Model
{
    protected $fillable = [
        'user_id', 'marketing_account_id', 'source_account_id', 'source_user_id',
        'order_item_id', 'layer', 'amount', 'status',
    ];

    protected $casts = ['amount' => 'decimal:2', 'layer' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (Commission $commission): void {
            if ($commission->layer < 1 || $commission->layer > 10) {
                throw new InvalidArgumentException('Commission layer must be between 1 and 10.');
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MarketingAccount::class, 'marketing_account_id');
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketingAccount::class, 'source_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
