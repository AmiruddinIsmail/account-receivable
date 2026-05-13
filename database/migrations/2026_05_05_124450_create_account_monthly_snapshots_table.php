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
        Schema::create('account_monthly_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->string('year_month')->index(); // YYYY-MM
            $table->integer('opening_balance_amt')->default(0);
            $table->integer('closing_balance_amt')->default(0);
            $table->integer('principal_balance_amt')->default(0);
            $table->integer('late_charge_balance_amt')->default(0);
            $table->integer('principal_billed_amt')->default(0);
            $table->integer('late_charge_billed_amt')->default(0);
            $table->integer('payment_received_amt')->default(0);
            $table->decimal('mia_score', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'year_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_monthly_snapshots');
    }
};
