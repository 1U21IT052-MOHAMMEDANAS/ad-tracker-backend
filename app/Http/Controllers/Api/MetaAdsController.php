<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Services\Meta\MetaApiException;
use App\Services\Meta\MetaClient;
use App\Services\Meta\MetaSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Throwable;

class MetaAdsController extends Controller
{
    /** Campaign table for one date range: a preset, or a custom since/until range. */
    public function index(Request $request, MetaSync $sync)
    {
        $data = $request->validate([
            'range' => ['nullable', Rule::in([...MetaSync::PRESETS, 'custom'])],
            'since' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'before_or_equal:until'],
            'until' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $range = $data['range'] ?? 'last_30d';
        $key = $range === 'custom' ? "{$data['since']}_{$data['until']}" : $range;

        $warning = $range === 'custom' ? $this->ensureCustomRange($sync, $key) : null;

        $campaigns = MetaCampaign::query()
            ->with(['adAccount', 'insights' => fn ($q) => $q->where('range_key', $key)])
            ->get()
            ->map(function (MetaCampaign $c) {
                $i = $c->insights->first();

                return [
                    'id' => $c->id,
                    'campaign_id' => $c->campaign_id,
                    'name' => $c->name,
                    'objective' => $c->objective,
                    'status' => strtolower((string) $c->effective_status),
                    'account' => $c->adAccount->only(['id', 'name', 'account_id', 'currency']),
                    'start_time' => $c->start_time?->format('Y-m-d H:i'),
                    'stop_time' => $c->stop_time?->format('Y-m-d H:i'),
                    'budget' => $c->daily_budget ?? $c->lifetime_budget,
                    'budget_type' => $c->daily_budget ? 'daily' : ($c->lifetime_budget ? 'lifetime' : null),
                    'budget_level' => $c->budget_level,
                    'results' => $i?->results,
                    'result_type' => $i?->result_type,
                    'cost_per_result' => $i?->cost_per_result,
                    'impressions' => $i->impressions ?? 0,
                    'reach' => $i->reach ?? 0,
                    'spend' => $i->spend ?? 0,
                    'fund' => $c->adAccount->fund,
                    'fund_left' => $c->adAccount->fund_left,
                ];
            })
            // Active campaigns first, then the biggest spenders.
            ->sortBy(fn ($c) => [$c['status'] === 'active' ? 0 : 1, -$c['spend']])
            ->values();

        return [
            'range_key' => $key,
            'last_synced_at' => MetaAdAccount::orderByDesc('synced_at')->first()?->synced_at?->toIso8601String(),
            'warning' => $warning,
            'accounts' => MetaAdAccount::orderBy('name')->get(),
            'campaigns' => $campaigns,
        ];
    }

    /** "Sync now" button: same as the scheduled `meta:sync`. */
    public function sync(MetaSync $sync)
    {
        $lock = Cache::lock('meta-sync', 600);
        if (! $lock->get()) {
            return response()->json(['message' => 'A sync is already running. Try again in a minute.'], 409);
        }

        set_time_limit(300);
        try {
            return $sync->run();
        } catch (MetaApiException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } finally {
            $lock->release();
        }
    }

    /** Token health, so the page can warn before the 60-day token expires. */
    public function status(MetaClient $client)
    {
        try {
            // Only cache a healthy result, so the warning clears as soon as Meta recovers.
            $info = Cache::get('meta-token-info') ?? $client->tokenInfo();
            if (! empty($info['valid'])) {
                Cache::put('meta-token-info', $info, 3600);
            }
        } catch (Throwable $e) {
            return ['configured' => $client->configured(), 'valid' => null, 'error' => $e->getMessage()];
        }

        if (! empty($info['expires_at'])) {
            $info['days_left'] = (int) floor(now()->diffInDays($info['expires_at'], false));
        }

        return $info;
    }

    /** Custom ranges aren't in the scheduled sync, so fetch them (at most every 15 minutes). */
    private function ensureCustomRange(MetaSync $sync, string $key): ?string
    {
        $errors = [];
        $accounts = MetaAdAccount::whereHas('campaigns')->get();

        foreach ($accounts as $account) {
            $fresh = $account->campaigns()
                ->whereHas('insights', fn ($q) => $q->where('range_key', $key)->where('synced_at', '>=', now()->subMinutes(15)))
                ->exists();
            if ($fresh) {
                continue;
            }

            try {
                $sync->syncInsights($account, $key);
            } catch (MetaApiException $e) {
                $errors[] = "{$account->name}: {$e->getMessage()}";
            }
        }

        return $errors ? implode(' ', $errors) : null;
    }
}
