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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('location_id'); //location id
            $table->bigInteger('transaction_id'); //transaction id
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('facility_name');
            $table->string('tax_number')->nullable();
            $table->string('invoice_address')->nullable();
            $table->string('invoice_City')->nullable();
            $table->string('invoice_Country')->nullable();
            $table->integer('postal_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
