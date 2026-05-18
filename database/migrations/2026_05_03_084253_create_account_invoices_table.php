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
        Schema::create('account_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->string('reference_no')->index();
            $table->date('occured_at');
            $table->date('due_at');
            $table->integer('principal_billed_amt');
            $table->integer('late_charge_billed_amt');
            $table->integer('principal_paid_amt')->default(0);
            $table->integer('late_charge_paid_amt')->default(0);
            $table->string('principal_status')->default('open');
            $table->string('late_charge_status')->default('open');
            $table->string('status')->default('open');
            $table->string('type')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_invoices');
    }
};
