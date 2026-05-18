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
        Schema::create('account_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->string('reference_no')->index();
            $table->date('occured_at');
            $table->integer('amount');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_refunds');
    }
};
