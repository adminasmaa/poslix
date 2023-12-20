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
        Schema::table('transactions_lines', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('product_id');
            // created_at and updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions_lines', function (Blueprint $table) {
            $table->dropColumn('sku');
            $table->dropTimestamps();
        });
    }
};
