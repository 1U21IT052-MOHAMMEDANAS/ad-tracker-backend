<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use Illuminate\Http\Request;

class FundController extends Controller
{
    public function index(Request $request)
    {
        return Fund::query()
            ->with('adAccount:id,name,currency')
            ->when($request->ad_account_id, fn ($q, $id) => $q->where('ad_account_id', $id))
            ->orderByDesc('fund_date')
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request)
    {
        $fund = Fund::create($this->validated($request));

        return response()->json($fund, 201);
    }

    public function show(Fund $fund)
    {
        return $fund->load('adAccount:id,name,currency');
    }

    public function update(Request $request, Fund $fund)
    {
        $fund->update($this->validated($request));

        return $fund;
    }

    public function destroy(Fund $fund)
    {
        $fund->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'ad_account_id' => ['required', 'exists:ad_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'fund_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
