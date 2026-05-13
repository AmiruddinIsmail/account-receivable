<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_aging_reports', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->string('year_month')->index();
            $table->integer('bucket_current')->default(0);
            $table->integer('bucket_30_days')->default(0); // 1 Month
            $table->integer('bucket_60_days')->default(0); // 2 Months
            $table->integer('bucket_90_days')->default(0); // 3 Months
            $table->integer('bucket_120_plus')->default(0); // 4+ Months
            $table->integer('total_outstanding');
            $table->timestamps();

            $table->unique(['account_id', 'year_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_aging_reports');
    }
};
