<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fund extends Model
{
    protected $fillable = ['ad_account_id', 'amount', 'fund_date', 'payment_method', 'reference', 'notes'];

    protected $casts = [
        'fund_date' => 'date:Y-m-d',
        'amount' => 'float',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }
}
