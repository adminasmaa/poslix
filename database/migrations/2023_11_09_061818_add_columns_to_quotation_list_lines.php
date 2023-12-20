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
        Schema::table('quotation_list_lines', function (Blueprint $table) {
            $table->bigInteger('variant_id')->nullable()->after('product_id');
            $table->double('price', 15, 2)->nullable()->after('variant_id');
            $table->string('sku')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_list_lines', function (Blueprint $table) {
            $table->dropColumn('variant_id');
            $table->dropColumn('price');
            $table->dropColumn('sku');
        });
    }
};
