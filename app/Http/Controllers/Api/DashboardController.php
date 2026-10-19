<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdDailyStat;
use App\Models\Fund;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $fundsAdded = (float) Fund::sum('amount');
        $allocated = (float) Ad::sum('fund_amount');
        $spent = (float) AdDailyStat::sum('amount_spent');
        $results = (int) AdDailyStat::sum('results');

        $last14 = DB::table('ad_daily_stats')
            ->select('stat_date', DB::raw('SUM(amount_spent) + 0 as spent'), DB::raw('SUM(results) + 0 as results'), DB::raw('SUM(impressions) + 0 as impressions'))
            ->whereDate('stat_date', '>=', now()->subDays(13)->toDateString())
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get();

        return [
            'accounts' => AdAccount::count(),
            'ads_total' => Ad::count(),
            'ads_by_status' => Ad::select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status'),
            'funds_added' => $fundsAdded,
            'fund_allocated' => $allocated,
            'total_spent' => $spent,
            'balance' => round($fundsAdded - $spent, 2),
            'total_results' => $results,
            'total_impressions' => (int) AdDailyStat::sum('impressions'),
            'cost_per_result' => $results > 0 ? round($spent / $results, 2) : null,
            'last_14_days' => $last14,
            'top_ads' => Ad::with('adAccount:id,name,currency')->withTotals()
                ->orderByDesc('total_spent')->limit(5)->get()->map->toSummary(),
        ];
    }
}
