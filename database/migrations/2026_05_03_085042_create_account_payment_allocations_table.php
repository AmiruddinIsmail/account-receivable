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
        Schema::create('account_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->string('reference_no')->index();
            $table->string('invoice_no')->index();
            $table->string('component');
            $table->integer('amount');
            $table->string('action');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_payment_allocations');
    }
};
