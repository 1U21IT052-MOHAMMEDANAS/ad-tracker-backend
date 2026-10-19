<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->restrictOnDelete();
            $table->string('campaign_name');
            $table->string('objective')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('budget', 12, 2)->default(0);
            $table->string('budget_type', 10)->default('daily');
            $table->decimal('fund_amount', 12, 2)->default(0)->comment('Fund allocated to this ad');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
