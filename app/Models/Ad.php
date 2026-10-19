<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ad extends Model
{
    public const STATUSES = ['active', 'paused', 'completed', 'draft'];

    protected $fillable = [
        'ad_account_id', 'campaign_name', 'objective', 'status', 'start_date', 'end_date',
        'budget', 'budget_type', 'fund_amount', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'budget' => 'float',
        'fund_amount' => 'float',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(AdDailyStat::class);
    }

    /** Adds summed metrics from the daily stats as total_* attributes. */
    public function scopeWithTotals(Builder $query): Builder
    {
        return $query
            ->withSum('dailyStats as total_results', 'results')
            ->withSum('dailyStats as total_impressions', 'impressions')
            ->withSum('dailyStats as total_reach', 'reach')
            ->withSum('dailyStats as total_spent', 'amount_spent')
            ->withMax('dailyStats as last_updated', 'stat_date');
    }

    /** Model data plus computed totals; requires the withTotals scope. */
    public function toSummary(): array
    {
        $spent = (float) ($this->total_spent ?? 0);
        $results = (int) ($this->total_results ?? 0);

        return array_merge($this->toArray(), [
            'total_results' => $results,
            'total_impressions' => (int) ($this->total_impressions ?? 0),
            'total_reach' => (int) ($this->total_reach ?? 0),
            'total_spent' => $spent,
            'cost_per_result' => $results > 0 ? round($spent / $results, 2) : null,
            'fund_remaining' => round($this->fund_amount - $spent, 2),
        ]);
    }
}
