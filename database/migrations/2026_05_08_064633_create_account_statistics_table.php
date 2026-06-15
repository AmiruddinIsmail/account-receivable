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
            $table->integer('tenure')->nullable()->index();
            $table->integer('subscription_amt')->nullable();

            // Counts
            $table->integer('invoices_count')->default(0);

            // Financial Totals (Lifetime)
            $table->unsignedBigInteger('billed_principal_amt')->default(0);
            $table->unsignedBigInteger('billed_late_charge_amt')->default(0);
            $table->unsignedBigInteger('billed_total_amt')->default(0);

            $table->unsignedBigInteger('payment_amt')->default(0);
            $table->unsignedBigInteger('refund_amt')->default(0);
            $table->unsignedBigInteger('credit_amt')->default(0);
            $table->unsignedBigInteger('credit_voided_amt')->default(0);

            // Allocations (Current state metrics)
            $table->unsignedBigInteger('payment_allocated_principal_amt')->default(0);
            $table->unsignedBigInteger('payment_allocated_late_charge_amt')->default(0);
            $table->unsignedBigInteger('payment_allocated_total_amt')->default(0);

            $table->unsignedBigInteger('credit_allocated_principal_amt')->default(0);
            $table->unsignedBigInteger('credit_allocated_late_charge_amt')->default(0);
            $table->unsignedBigInteger('credit_allocated_total_amt')->default(0);

            // Current Balances
            $table->unsignedBigInteger('remaining_principal_amt')->default(0);
            $table->unsignedBigInteger('remaining_late_charge_amt')->default(0);
            $table->unsignedBigInteger('remaining_balance_amt')->default(0);
            $table->bigInteger('net_balance_amt')->default(0);

            $table->unsignedBigInteger('unused_overpayment_amt')->default(0);
            $table->unsignedBigInteger('unused_credit_amt')->default(0);

            // Executive Reporting & MIA
            $table->integer('mia_score')->default(0)->index();
            $table->boolean('is_delinquent')->default(false);
            $table->string('risk_level')->default('Low');
            $table->decimal('collection_rate', 12, 2)->default(0);

            // Timestamps
            $table->timestamp('last_payment_at')->nullable()->index();
            $table->timestamp('last_invoice_at')->nullable()->index();
            $table->timestamp('oldest_open_principal_invoice_at')->nullable()->index();
            $table->timestamp('oldest_open_late_charge_invoice_at')->nullable()->index();
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
