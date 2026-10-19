<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdDailyStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdDailyStatController extends Controller
{
    /**
     * Ads running on the given date, each with its stats for that date (if entered).
     * Drives the "Daily Update" sheet.
     */
    public function index(Request $request)
    {
        $date = $request->validate(['date' => ['required', 'date']])['date'];

        return Ad::query()
            ->with('adAccount:id,name,currency')
            ->with(['dailyStats' => fn ($q) => $q->whereDate('stat_date', $date)])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->when($request->ad_account_id, fn ($q, $id) => $q->where('ad_account_id', $id))
            ->orderBy('campaign_name')
            ->get()
            ->map(fn (Ad $ad) => [
                'ad' => array_merge($ad->only(['id', 'campaign_name', 'status', 'budget', 'budget_type']), [
                    'start_date' => $ad->start_date->format('Y-m-d'),
                    'end_date' => $ad->end_date->format('Y-m-d'),
                    'ad_account' => $ad->adAccount,
                ]),
                'stat' => $ad->dailyStats->first(),
            ]);
    }

    /** Create or overwrite the stats for one ad on one date. */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'ad_id' => ['required', 'exists:ads,id'],
        ]);

        return response()->json($this->save($data), 201);
    }

    /** Save many rows at once from the Daily Update sheet. */
    public function bulk(Request $request)
    {
        $entries = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.ad_id' => ['required', 'exists:ads,id'],
        ] + collect($this->rules())
            ->mapWithKeys(fn ($rule, $key) => ["entries.*.$key" => $rule])
            ->all())['entries'];

        $saved = DB::transaction(fn () => collect($entries)->map(fn ($e) => $this->save($e)));

        return ['saved' => $saved->count()];
    }

    public function update(Request $request, AdDailyStat $dailyStat)
    {
        $data = $request->validate($this->rules());
        $dailyStat->update(collect($data)->except('status')->all());
        if (! empty($data['status'])) {
            $dailyStat->ad->update(['status' => $data['status']]);
        }

        return $dailyStat;
    }

    public function destroy(AdDailyStat $dailyStat)
    {
        $dailyStat->delete();

        return response()->noContent();
    }

    private function rules(): array
    {
        return [
            'stat_date' => ['required', 'date'],
            'results' => ['required', 'integer', 'min:0'],
            'impressions' => ['required', 'integer', 'min:0'],
            'reach' => ['required', 'integer', 'min:0'],
            'amount_spent' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            // Optional: also update the ad's status as part of the daily update.
            'status' => ['nullable', Rule::in(Ad::STATUSES)],
        ];
    }

    private function save(array $data): AdDailyStat
    {
        $stat = AdDailyStat::updateOrCreate(
            ['ad_id' => $data['ad_id'], 'stat_date' => $data['stat_date']],
            collect($data)->only(['results', 'impressions', 'reach', 'amount_spent', 'notes'])->all(),
        );

        if (! empty($data['status'])) {
            Ad::whereKey($data['ad_id'])->update(['status' => $data['status']]);
        }

        return $stat;
    }
}
