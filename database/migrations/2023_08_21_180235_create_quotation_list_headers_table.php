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
        Schema::create('quotation_list_headers', function (Blueprint $table) {
            $table->id();
            $table->integer('location_id');
            $table->string('status');
            $table->string('action')->nullable();
            $table->integer('employ_id');
            $table->integer('customer_id');
            $table->timestamps();
          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_list_headers');
    }
};
