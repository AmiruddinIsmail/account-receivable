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
        Schema::create('account_statistics', function (Blueprint $table) {
            $table->string('account_id')->primary();

            $table->bigInteger('principal_billed_amt')->default(0);

            // Counts
            $table->integer('invoices_count')->default(0);

            // Financial Totals (Lifetime)
            $table->bigInteger('total_principal_billed_amt')->default(0);
            $table->bigInteger('total_late_charge_billed_amt')->default(0);
            $table->bigInteger('total_invoices_amt')->default(0);

            $table->bigInteger('total_payments_amt')->default(0);
            $table->bigInteger('total_refunded_amt')->default(0);
            $table->bigInteger('total_credits_amt')->default(0);

            // Allocations (Current state metrics)
            $table->bigInteger('total_allocated_principal_amt')->default(0);
            $table->bigInteger('total_allocated_late_charge_amt')->default(0);
            $table->bigInteger('total_allocated_payments_amt')->default(0);

            $table->bigInteger('total_allocated_principal_credits_amt')->default(0);
            $table->bigInteger('total_allocated_late_charge_credits_amt')->default(0);
            $table->bigInteger('total_allocated_credits_amt')->default(0);

            // Current Balances
            $table->bigInteger('remaining_principal_amt')->default(0);
            $table->bigInteger('remaining_late_charge_amt')->default(0);
            $table->bigInteger('remaining_balance_amt')->default(0);
            $table->bigInteger('unallocated_overpayment_amt')->default(0);

            // Executive Reporting & MIA
            $table->integer('mia_score')->default(0);
            $table->boolean('is_delinquent')->default(false);
            $table->string('risk_level')->default('Low');
            $table->decimal('collection_rate', 12, 2)->default(0);

            // Timestamps
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('last_invoice_at')->nullable();
            $table->timestamp('oldest_open_principal_invoice_at')->nullable();
            $table->timestamp('oldest_open_late_charge_invoice_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_statistics');
    }
};
