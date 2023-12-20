<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotation_list_headers', function (Blueprint $table) {
            $table->string('type')->nullable()->after('action');
            $table->enum('payment_status', ['paid', 'partially_paid', 'not_paid'])->default('not_paid')->after('type');
            $table->string('notes')->nullable()->after('payment_status');
            $table->enum('tax_type', ['fixed', 'percentage'])->default('percentage')->after('notes');
            $table->double('tax_amount')->nullable()->after('tax_type');
            $table->double('total_price')->nullable()->after('tax_amount');
            $table->enum('discount_type', ['fixed', 'percentage'])->default('percentage')->after('total_price');
            $table->double('discount_amount')->nullable()->after('discount_type');
            $table->integer('exchange_rate')->default(1)->after('discount_amount');

            // supplier_id
            $table->unsignedBigInteger('supplier_id')->nullable()->after('customer_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_list_headers', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_list_headers', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('quotation_list_headers', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('quotation_list_headers', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('quotation_list_headers', 'tax_type')) {
                $table->dropColumn('tax_type');
            }
            if (Schema::hasColumn('quotation_list_headers', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('quotation_list_headers', 'total_price')) {
                $table->dropColumn('total_price');
            }
            if (Schema::hasColumn('quotation_list_headers', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
            if (Schema::hasColumn('quotation_list_headers', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('quotation_list_headers', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
            if (Schema::hasColumn('quotation_list_headers', 'supplier_id')) {
                $table->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('quotation_list_headers', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
