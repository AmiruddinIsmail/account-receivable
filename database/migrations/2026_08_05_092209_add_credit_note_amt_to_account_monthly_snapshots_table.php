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
        Schema::table('account_monthly_snapshots', function (Blueprint $table) {
            $table->integer('credit_note_amt')->nullable()->after('payment_received_amt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_monthly_snapshots', function (Blueprint $table) {
            $table->dropColumn('credit_note_amt');
        });
    }
};
