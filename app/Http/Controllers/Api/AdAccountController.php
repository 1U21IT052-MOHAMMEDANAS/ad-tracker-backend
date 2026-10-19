<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdAccountController extends Controller
{
    public function index()
    {
        $accounts = AdAccount::query()
            ->withCount('ads')
            ->withSum('funds as funds_added', 'amount')
            ->withSum('ads as fund_allocated', 'fund_amount')
            ->withSum('dailyStats as total_spent', 'amount_spent')
            ->orderBy('name')
            ->get();

        return $accounts->map(fn (AdAccount $a) => $this->withBalances($a));
    }

    public function store(Request $request)
    {
        $account = AdAccount::create($this->validated($request));

        return response()->json($account, 201);
    }

    public function show(AdAccount $adAccount)
    {
        return $adAccount;
    }

    public function update(Request $request, AdAccount $adAccount)
    {
        $adAccount->update($this->validated($request));

        return $adAccount;
    }

    public function destroy(AdAccount $adAccount)
    {
        if ($adAccount->ads()->exists() || $adAccount->funds()->exists()) {
            return response()->json([
                'message' => 'This account still has ads or fund entries. Delete those first, or mark the account inactive.',
            ], 422);
        }

        $adAccount->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function withBalances(AdAccount $a): array
    {
        $added = (float) ($a->funds_added ?? 0);
        $allocated = (float) ($a->fund_allocated ?? 0);
        $spent = (float) ($a->total_spent ?? 0);

        return array_merge($a->toArray(), [
            'funds_added' => $added,
            'fund_allocated' => $allocated,
            'total_spent' => $spent,
            'unallocated' => round($added - $allocated, 2),
            'balance' => round($added - $spent, 2),
        ]);
    }
}
