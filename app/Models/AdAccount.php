<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AdAccount extends Model
{
    protected $fillable = ['name', 'account_id', 'business_name', 'currency', 'status', 'notes'];

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
    }

    public function dailyStats(): HasManyThrough
    {
        return $this->hasManyThrough(AdDailyStat::class, Ad::class);
    }
}
