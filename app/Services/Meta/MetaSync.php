<?php

namespace App\Services\Meta;

use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Models\MetaCampaignInsight;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Copies ad accounts, campaigns and insights from Meta into the meta_* tables. */
class MetaSync
{
    /** Date ranges refreshed by every scheduled sync. Custom ranges are fetched on demand. */
    public const PRESETS = ['today', 'yesterday', 'last_7d', 'last_30d', 'this_month', 'maximum'];

    /** Currencies Meta reports without a minor unit (all others are in cents/paise). */
    private const NO_MINOR_UNIT = ['CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'JPY', 'KRW', 'PYG', 'TWD', 'VND'];

    private const RESULT_LABELS = [
        'onsite_conversion.messaging_conversation_started_7d' => 'Messaging conversations started',
        'onsite_conversion.total_messaging_connection' => 'Messaging connections',
        'click_to_call_native_call_placed' => 'Calls placed',
        'lead' => 'Leads',
        'onsite_conversion.lead_grouped' => 'Leads',
        'offsite_conversion.fb_pixel_lead' => 'Website leads',
        'offsite_conversion.fb_pixel_purchase' => 'Website purchases',
        'link_click' => 'Link clicks',
        'landing_page_view' => 'Landing page views',
        'post_engagement' => 'Post engagements',
        'video_thruplay_watched_actions' => 'ThruPlays',
        'like' => 'Page likes',
        'reach' => 'Reach',
        'impressions' => 'Impressions',
    ];

    public function __construct(private MetaClient $client)
    {
    }

    /**
     * Full sync: accounts, campaigns and every preset range.
     *
     * @return array{accounts: int, campaigns: int, errors: string[]}
     */
    public function run(): array
    {
        $errors = [];
        $accounts = $this->syncAccounts();

        foreach ($accounts as $account) {
            try {
                $this->syncCampaigns($account);
                foreach (self::PRESETS as $preset) {
                    $this->syncInsights($account, $preset);
                }
            } catch (MetaApiException $e) {
                $errors[] = "{$account->name}: {$e->getMessage()}";
                Log::warning('Meta sync failed for '.$account->account_id, ['error' => $e->getMessage()]);
            }
        }

        return [
            'accounts' => $accounts->count(),
            'campaigns' => MetaCampaign::count(),
            'errors' => $errors,
        ];
    }

    /** Every ad account the token can see, including ones other people shared with you. */
    public function syncAccounts()
    {
        $rows = $this->client->getAll('me/adaccounts', [
            'fields' => 'account_id,name,currency,account_status,is_prepay_account,spend_cap,amount_spent,balance',
        ]);

        $seen = [];
        foreach ($rows as $row) {
            $currency = $row['currency'] ?? 'INR';
            $values = [
                'name' => $row['name'] ?? $row['id'],
                'currency' => $currency,
                'account_status' => $row['account_status'] ?? null,
                'is_prepay' => (bool) ($row['is_prepay_account'] ?? false),
                'spend_cap' => $this->minor($row['spend_cap'] ?? null, $currency),
                'amount_spent' => $this->minor($row['amount_spent'] ?? 0, $currency),
                'balance' => $this->minor($row['balance'] ?? 0, $currency),
                'synced_at' => now(),
            ];

            // Asked separately: viewers without billing access can't read it, and it shouldn't fail the whole list.
            try {
                $funding = $this->client->get($row['id'], ['fields' => 'funding_source_details'])['funding_source_details'] ?? [];
                $values['funding_display'] = $funding['display_string'] ?? null;
                $values['available_funds'] = $this->availableBalance($values['funding_display']);
            } catch (MetaApiException) {
                $values['funding_display'] = null;
                $values['available_funds'] = null;
            }

            MetaAdAccount::updateOrCreate(['account_id' => $row['id']], $values);
            $seen[] = $row['id'];
        }

        // Accounts you no longer have access to.
        MetaAdAccount::whereNotIn('account_id', $seen)->delete();

        return MetaAdAccount::orderBy('name')->get();
    }

    public function syncCampaigns(MetaAdAccount $account): void
    {
        $campaigns = $this->client->getAll($account->account_id.'/campaigns', [
            'fields' => 'id,name,objective,effective_status,start_time,stop_time,daily_budget,lifetime_budget',
        ]);
        $adSets = collect($this->client->getAll($account->account_id.'/adsets', [
            'fields' => 'campaign_id,effective_status,daily_budget,lifetime_budget,end_time',
        ]))->reject(fn ($s) => in_array($s['effective_status'] ?? '', ['DELETED', 'ARCHIVED']))->groupBy('campaign_id');

        $seen = [];
        foreach ($campaigns as $c) {
            if (in_array($c['effective_status'] ?? '', ['DELETED', 'ARCHIVED'])) {
                continue;
            }

            $sets = $adSets->get($c['id'], collect());
            $daily = $c['daily_budget'] ?? null;
            $lifetime = $c['lifetime_budget'] ?? null;
            $level = ($daily || $lifetime) ? 'campaign' : null;

            // Ad set budgets (ABO): add up the active ad sets, like Ads Manager's total.
            if (! $level && $sets->isNotEmpty()) {
                $active = $sets->where('effective_status', 'ACTIVE');
                $pool = $active->isNotEmpty() ? $active : $sets;
                $daily = $pool->sum(fn ($s) => (int) ($s['daily_budget'] ?? 0)) ?: null;
                $lifetime = $daily ? null : ($pool->sum(fn ($s) => (int) ($s['lifetime_budget'] ?? 0)) ?: null);
                $level = 'ad_set';
            }

            $stop = $c['stop_time'] ?? null;
            if (! $stop && $sets->isNotEmpty() && $sets->every(fn ($s) => ! empty($s['end_time']))) {
                $stop = $sets->max('end_time');
            }

            MetaCampaign::updateOrCreate(['campaign_id' => $c['id']], [
                'meta_ad_account_id' => $account->id,
                'name' => $c['name'],
                'objective' => $c['objective'] ?? null,
                'effective_status' => $c['effective_status'] ?? null,
                'start_time' => $this->localTime($c['start_time'] ?? null),
                'stop_time' => $this->localTime($stop),
                'daily_budget' => $this->minor($daily, $account->currency),
                'lifetime_budget' => $this->minor($lifetime, $account->currency),
                'budget_level' => $level,
                'synced_at' => now(),
            ]);
            $seen[] = $c['id'];
        }

        $account->campaigns()->whereNotIn('campaign_id', $seen)->delete();
    }

    /** Results, impressions, reach and spend per campaign for one date range. */
    public function syncInsights(MetaAdAccount $account, string $rangeKey): void
    {
        $rows = collect($this->client->getAll($account->account_id.'/insights', array_merge([
            'level' => 'campaign',
            'fields' => 'campaign_id,results,cost_per_result,impressions,reach,spend',
        ], self::rangeParams($rangeKey))))->keyBy('campaign_id');

        $now = now();
        $upserts = $account->campaigns()->get(['id', 'campaign_id'])->map(function (MetaCampaign $c) use ($rows, $rangeKey, $now) {
            $row = $rows->get($c->campaign_id, []);
            $indicator = $row['results'][0]['indicator'] ?? null;

            return [
                'meta_campaign_id' => $c->id,
                'range_key' => $rangeKey,
                'date_start' => $row['date_start'] ?? null,
                'date_stop' => $row['date_stop'] ?? null,
                'results' => $row['results'][0]['values'][0]['value'] ?? null,
                'result_type' => $indicator ? $this->resultLabel($indicator) : null,
                'cost_per_result' => $row['cost_per_result'][0]['values'][0]['value'] ?? null,
                'impressions' => (int) ($row['impressions'] ?? 0),
                'reach' => (int) ($row['reach'] ?? 0),
                'spend' => (float) ($row['spend'] ?? 0),
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        MetaCampaignInsight::upsert($upserts, ['meta_campaign_id', 'range_key'], [
            'date_start', 'date_stop', 'results', 'result_type', 'cost_per_result', 'impressions', 'reach', 'spend',
            'synced_at', 'updated_at',
        ]);
    }

    /** Graph API params for a preset ("last_30d") or custom range key ("2026-10-01_2026-10-20"). */
    public static function rangeParams(string $rangeKey): array
    {
        if (in_array($rangeKey, self::PRESETS, true)) {
            return ['date_preset' => $rangeKey];
        }

        [$since, $until] = explode('_', $rangeKey);

        return ['time_range' => json_encode(['since' => $since, 'until' => $until])];
    }

    /** Account and budget amounts come in the currency's minor unit (paise for INR). */
    private function minor($value, string $currency): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array($currency, self::NO_MINOR_UNIT, true) ? (float) $value : round($value / 100, 2);
    }

    /** "Available balance (₹33.74 INR)" -> 33.74 */
    private function availableBalance(?string $display): ?float
    {
        if (! $display || ! Str::contains($display, 'balance', true)) {
            return null;
        }

        return preg_match('/(\d[\d,]*(?:\.\d+)?)/', $display, $m) ? (float) str_replace(',', '', $m[1]) : null;
    }

    /** Keep the wall-clock time in the ad account's timezone, as Ads Manager shows it. */
    private function localTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }

    private function resultLabel(string $indicator): string
    {
        $type = Str::after($indicator, ':');

        return self::RESULT_LABELS[$type] ?? Str::of($type)->afterLast('.')->replace('_', ' ')->ucfirst()->toString();
    }
}
