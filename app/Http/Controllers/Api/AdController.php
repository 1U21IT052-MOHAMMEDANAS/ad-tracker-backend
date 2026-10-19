<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
    public function index(Request $request)
    {
        $ads = Ad::query()
            ->with('adAccount:id,name,currency')
            ->withTotals()
            ->when($request->ad_account_id, fn ($q, $id) => $q->where('ad_account_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('campaign_name', 'like', "%{$s}%"))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return $ads->map->toSummary();
    }

    public function store(Request $request)
    {
        $ad = Ad::create($this->validated($request));

        return response()->json($ad, 201);
    }

    public function show(int $id)
    {
        $ad = Ad::with('adAccount:id,name,currency')->withTotals()->findOrFail($id);

        return array_merge($ad->toSummary(), [
            'daily_stats' => $ad->dailyStats()->orderByDesc('stat_date')->get(),
        ]);
    }

    public function update(Request $request, Ad $ad)
    {
        $ad->update($this->validated($request));

        return $ad;
    }

    public function destroy(Ad $ad)
    {
        $ad->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'ad_account_id' => ['required', 'exists:ad_accounts,id'],
            'campaign_name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Ad::STATUSES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'budget' => ['required', 'numeric', 'min:0'],
            'budget_type' => ['required', Rule::in(['daily', 'lifetime'])],
            'fund_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
