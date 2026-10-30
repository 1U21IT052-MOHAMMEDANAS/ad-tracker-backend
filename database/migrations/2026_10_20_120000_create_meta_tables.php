<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Live data pulled from the Meta Marketing API by `php artisan meta:sync`.
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->unique()->comment('Meta ad account ID, e.g. act_123456');
            $table->string('name');
            $table->string('currency', 10)->default('INR');
            $table->unsignedSmallInteger('account_status')->nullable();
            $table->boolean('is_prepay')->default(false);
            $table->decimal('spend_cap', 14, 2)->nullable();
            $table->decimal('amount_spent', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('available_funds', 14, 2)->nullable();
            $table->string('funding_display')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_id')->unique();
            $table->string('name');
            $table->string('objective')->nullable();
            $table->string('effective_status', 40)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->decimal('daily_budget', 14, 2)->nullable();
            $table->decimal('lifetime_budget', 14, 2)->nullable();
            $table->string('budget_level', 10)->nullable()->comment('campaign or ad_set');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_campaign_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('range_key', 40)->comment('Date preset (last_30d) or custom range (2026-10-01_2026-10-20)');
            $table->date('date_start')->nullable();
            $table->date('date_stop')->nullable();
            $table->decimal('results', 14, 2)->nullable();
            $table->string('result_type')->nullable();
            $table->decimal('cost_per_result', 14, 4)->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->decimal('spend', 14, 2)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['meta_campaign_id', 'range_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_campaign_insights');
        Schema::dropIfExists('meta_campaigns');
        Schema::dropIfExists('meta_ad_accounts');
    }
};
