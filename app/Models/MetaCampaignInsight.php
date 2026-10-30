<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaCampaignInsight extends Model
{
    protected $fillable = [
        'meta_campaign_id', 'range_key', 'date_start', 'date_stop', 'results', 'result_type', 'cost_per_result',
        'impressions', 'reach', 'spend', 'synced_at',
    ];

    protected $casts = [
        'date_start' => 'date:Y-m-d',
        'date_stop' => 'date:Y-m-d',
        'results' => 'float',
        'cost_per_result' => 'float',
        'impressions' => 'integer',
        'reach' => 'integer',
        'spend' => 'float',
        'synced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class, 'meta_campaign_id');
    }
}
