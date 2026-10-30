<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAdAccount extends Model
{
    /** Meta's account_status codes. */
    public const STATUSES = [
        1 => 'active',
        2 => 'disabled',
        3 => 'unsettled',
        7 => 'pending review',
        8 => 'pending settlement',
        9 => 'grace period',
        100 => 'pending closure',
        101 => 'closed',
    ];

    protected $fillable = [
        'account_id', 'name', 'currency', 'account_status', 'is_prepay', 'spend_cap', 'amount_spent',
        'balance', 'available_funds', 'funding_display', 'synced_at',
    ];

    protected $casts = [
        'is_prepay' => 'boolean',
        'spend_cap' => 'float',
        'amount_spent' => 'float',
        'balance' => 'float',
        'available_funds' => 'float',
        'synced_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'fund', 'fund_left'];

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->account_status] ?? 'unknown';
    }

    /** Total funds on the account: the spend limit (for prepaid accounts, the total added). */
    public function getFundAttribute(): ?float
    {
        return $this->spend_cap ?: null;
    }

    /** Prepaid: Meta's "Available balance". Otherwise what's left under the spend limit. */
    public function getFundLeftAttribute(): ?float
    {
        if ($this->available_funds !== null) {
            return $this->available_funds;
        }

        return $this->spend_cap ? round($this->spend_cap - $this->amount_spent, 2) : null;
    }
}
