<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaCampaign extends Model
{
    protected $fillable = [
        'meta_ad_account_id', 'campaign_id', 'name', 'objective', 'effective_status', 'start_time', 'stop_time',
        'daily_budget', 'lifetime_budget', 'budget_level', 'synced_at',
    ];

    protected $casts = [
        // Wall-clock time in the ad account's timezone (see MetaSync::localTime).
        'start_time' => 'datetime:Y-m-d H:i',
        'stop_time' => 'datetime:Y-m-d H:i',
        'daily_budget' => 'float',
        'lifetime_budget' => 'float',
        'synced_at' => 'datetime',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaCampaignInsight::class);
    }
}
