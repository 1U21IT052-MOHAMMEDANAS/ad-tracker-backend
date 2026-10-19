<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdDailyStat extends Model
{
    protected $fillable = ['ad_id', 'stat_date', 'results', 'impressions', 'reach', 'amount_spent', 'notes'];

    protected $casts = [
        'stat_date' => 'date:Y-m-d',
        'results' => 'integer',
        'impressions' => 'integer',
        'reach' => 'integer',
        'amount_spent' => 'float',
    ];

    protected $appends = ['cost_per_result'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function getCostPerResultAttribute(): ?float
    {
        return $this->results > 0 ? round($this->amount_spent / $this->results, 2) : null;
    }
}
