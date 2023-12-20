<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Variation;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    use GeneralTrait;

    public function index(Request $request, $order_id)
    {
        $sales = Transaction::with('customer')
            ->with('stocks')
            ->find($order_id);
        if (!$sales) {
            return customResponse("Order not found", 404);
        }
        $relatedSales = Transaction::with('products')
            ->with('customer')
            ->with('stocks')
            ->where('related_transaction_id', $order_id)
            ->get();
        $transactionLines = TransactionLine::where('transaction_id', $order_id)->get();
        $listLines = [];
        foreach ($transactionLines as $transactionLine) {
            $product = Product::where('id', $transactionLine->product_id)
                ->select('id', 'name', 'sku')
                ->first();
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
        $sales->products = $products;
        $sales->related_invoices = $relatedSales ?? [];
        return customResponse($sales, 200);
    }

    public function update(Request $request, $order_id)
    {
        $transaction = Transaction::find($order_id);
        if (!$transaction) {
            return customResponse("Order not found", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "data" => "required|array",
            "data.*.update_type" => "required|string:in:add_product,update_product,remove_product",
            "data.*.cart" => "required|array",
            "data.*.cart.*.product_id" => "required|numeric:exists:products,id",
            "data.*.cart.*.variation_id" => "nullable|numeric:exists:variations,id",
            // required if update_type is add_product or update_product
            "data.*.cart.*.qty" => "required_if:update_type,add_product,update_product|numeric|min:1",
        ]);
        if ($validator) {
            return $validator;
        }
        $arrayMessage = [];
        foreach ($request->data as $key => $data) {
            $data = (object) $data;
            $data->cart = array_map(function ($cart) {
                if (!isset($cart['note'])) {
                    $cart['note'] = 'update order';
                }
                return $cart;
            }, $data->cart);
            if ($data->update_type == "add_product") {
                // add product
                foreach ($data->cart as $cart) {
                    $productData = $this->getVariationAndStocks($cart);
                    $variation = $productData['variation'];
                    $stock_id = $productData['stock_id'];
                    $product = $productData['product'];
                    $transaction->products()->attach($cart['product_id'], [
                        "variation_id" => $variation ? $variation->id : null,
                        "qty" => $cart['qty'],
                        "tax_amount" => $data->tax_amount ?? 0,
                        "cost" => $variation ? $variation->cost : $product->cost_price,
                        "price" => $variation ? $variation->price : $product->sell_price,
                        "status" => "pending",
                        "stock_id" => $stock_id,
                        "note" => $cart['note'],
                    ]);
                }
            } else if ($data->update_type == "update_product") {
                // update product
                foreach ($data->cart as $cart) {
                    $productData = $this->getVariationAndStocks($cart);
                    $variation = $productData['variation'];
                    $stock_id = $productData['stock_id'];
                    $product = $productData['product'];
                    $data = $transaction->products()->updateExistingPivot($cart['product_id'], [
                        "variation_id" => $variation ? $variation->id : null,
                        "qty" => $cart['qty'],
                        "tax_amount" => $data->tax_amount ?? 0,
                        "cost" => $variation ? $variation->cost : $product->cost_price,
                        "price" => $variation ? $variation->price : $product->sell_price,
                        "status" => "pending",
                        "stock_id" => $stock_id,
                        "note" => $cart['note'],
                    ]);
                    if (!$data) {
                        $arrayMessage[] = "Product not found at index " . $key;
                    }
                }
            } else if ($data->update_type == "remove_product") {
                // remove product
                foreach ($data->cart as $cart) {
                    $data = $transaction->products()->detach($cart['product_id']);
                    if (!$data) {
                        $arrayMessage[] = "Product not found at index " . $key;
                    }
                }
            } else {
                $arrayMessage[] = "Invalid update type. It should be add_product, update_product or remove_product at index " . $key;
            }
        }
        if (count($arrayMessage) > 0) {
            return customResponse($arrayMessage, 400);
        }
        return customResponse("Order updated successfully", 200);
    }

    public function destroy(Request $request, $order_id)
    {
        $transaction = Transaction::find($order_id);
        if (!$transaction) {
            return customResponse("Order not found", 404);
        }
        $transaction->products()->detach();
        $destroy = $transaction->delete();
        if (!$destroy) {
            return customResponse("Can not delete order", 404);
        }
        return customResponse("Order deleted successfully", 200);
    }

    public function completePayment(Request $request, $order_id)
    {
        $transaction = Transaction::find($order_id);
        if (!$transaction) {
            return customResponse("Order not found", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "amount" => "required|min:1",
            "payment_type" => "required|string:in:cash,card,check,bank",
        ]);
        if ($validator) {
            return $validator;
        }
        $payments = Payment::where('transaction_id', $order_id);
        $paymentsSum = $payments->sum('amount');
        if ($paymentsSum >= $transaction->total_price) {
            $transaction->update([
                "payment_status" => "paid",
            ]);
            return customResponse("Order already paid", 400);
        } else if ($request->amount > $transaction->total_price - $paymentsSum) {
            $remainingAmount = $transaction->total_price - $paymentsSum;
            return customResponse("Amount is greater than the remaining amount => " . $remainingAmount, 400);
        } else if ($request->amount == $transaction->total_price - $paymentsSum) {
            $transaction->payment()->create([
                "payment_type" => $request->payment_type,
                "amount" => $request->amount,
                "created_by" => auth()->user()->id,
                "notes" => 'complete payment',
            ]);
            $transaction->update([
                "payment_status" => "paid",
            ]);
        } else {
            $transaction->payment()->create([
                "payment_type" => $request->payment_type,
                "amount" => $request->amount,
                "created_by" => auth()->user()->id,
                "notes" => 'complete payment',
            ]);
        }
        return customResponse("Payment added successfully", 200);
    }

    private function getVariationAndStocks($cart)
    {
        $product = Product::find($cart['product_id']);

        if ($product->type == "single") {
            $variation = null;
        } else if ($product->type == "variable") {
            $variation = Variation::find($cart['variation_id']);
        } else {
            return customResponse("Product type not found", 404);
        }

        $stocks = $product->stocks;
        $qty = 0;
        foreach ($stocks as $stock) {
            $qty += $stock->qty_received - $stock->qty_sold;
        }
        if ($qty < $cart['qty']) {
            return customResponse("There is not enough qty for this product", 400);
        }
        // update the available stock and get the updated stock id
        if ($product->type == "single") {
            $stock_id = $this->updateStock($product->id, null, $cart['qty']);
        } else if ($product->type == "variable") {
            $stock_id = $this->updateStock($product->id, $variation->id, $cart['qty']);
        } else {
            $stock_id = $this->updateStock($product->id, null, $cart['qty']);
        }

        if (!$stock_id) {
            return customResponse("There is not enough qty for this product", 400);
        }
        return [
            "variation" => $variation,
            "stock_id" => $stock_id,
            'product' => $product
        ];
    }
}
