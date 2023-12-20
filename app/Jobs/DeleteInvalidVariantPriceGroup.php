<?php

namespace App\Jobs;

use App\Models\Variation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteInvalidVariantPriceGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info("DeleteInvalidVariantPriceGroup job started");
        try {
            $products = \DB::table('product_group_price')
                ->whereNotNull('variant_id')
                ->get();
            foreach ($products as $product) {
                $variant = Variation::where('id', $product->variant_id)
                    ->where('parent_id', $product->product_id)
                    ->first();
                if (!$variant) {
                    \DB::table('product_group_price')
                        ->where('variant_id', $product->variant_id)
                        ->where('product_id', $product->product_id)
                        ->delete();
                }
            }
        } catch (\Exception $e) {
            \Log::error("DeleteInvalidVariantPriceGroup: " . $e->getMessage() . " - " . $e->getLine() . " - " . $e->getFile() . " - " . $e->getTraceAsString());
        }
    }
}
