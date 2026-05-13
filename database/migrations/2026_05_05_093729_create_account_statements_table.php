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
        Schema::create('account_statements', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->string('reference_no')->index();
            $table->string('type'); // Invoice, Payment, Credit, Refund, Late Charge
            $table->date('occured_at');
            $table->integer('debit_amt')->default(0);
            $table->integer('credit_amt')->default(0);
            $table->integer('balance_impact'); // Positive for Debit, Negative for Credit
            $table->integer('running_balance');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_statements');
    }
};
