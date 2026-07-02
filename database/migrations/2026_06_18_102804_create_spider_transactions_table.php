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
        Schema::create('spider_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date_at');
            $table->string('type');
            $table->string('mandate');
            $table->string('running_id');
            $table->string('reference_no')->unique();
            $table->string('customer_id');
            $table->double('amount');
            $table->integer('tenure');
            $table->double('subscription_amt');
            $table->integer('sort_order');
            $table->double('program_fee');
            $table->double('amt_diff');
            $table->string('description');
            $table->boolean('multi_device');
            $table->integer('security_deposit_count')->default(0);
            $table->string('contract_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spider_transactions');
    }
};
