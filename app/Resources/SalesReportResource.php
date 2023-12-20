<?php

namespace App\Resources;

use App\Models\Product;
use App\Models\TransactionLine;
use App\Models\Variation;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
{

    public function toArray($request)
    {
        $subTotal = round($this->sub_total, 2);
        $due = round($subTotal - $this->payment_amount, 2);
        $tax = round($this->tax, 2);

        $transactionLines = TransactionLine::where('transaction_id', $this->id)->get();
        $listLines = [];
        foreach ($transactionLines as $transactionLine) {
            $product = Product::where('id', $transactionLine->product_id)
                ->select('id', 'name', 'sku')
                ->first();
            if (!$product) {
                continue;
            }
            $variantName = Variation::where('id', $transactionLine->variation_id)
                ->select('name')
                ->first();
            $listLines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name . ' ' . ($variantName->name ?? ''),
                'product_sku' => $transactionLine->sku ?? $product->sku,
                'product_price' => $transactionLine->price ?? 0,
                'product_qty' => $transactionLine->qty,
                'variant_id' => $transactionLine->variation_id ?? null,
            ];
        }
        $products = $listLines;

        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'user_name' => $this->user_first_name . ' ' . $this->user_last_name,
            'contact_name' => $this->contact_first_name . ' ' . $this->contact_last_name,
            'contact_mobile' => $this->contact_mobile,
            'sub_total' => round(($subTotal - $tax),2),
            'tax' => $this->tax,
            'total' => round($subTotal, 2),
            'payed' => round($this->payment_amount, 2),
            'due' => max($due, 0),
            'discount' => $this->discount,
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'transaction_status' => $this->transaction_status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'type' => $this->type,
            'products' => $products,
        ];
    }

}
