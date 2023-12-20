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
        if (Schema::hasTable('print_settings')) {
            return;
        }
        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('connection');
            $table->string('ip');
            $table->enum('print_type', ['A4', 'receipt']);
            $table->boolean('status')->default(1);
            $table->foreignId('location_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_settings');
    }
};
