<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\PricingGroup;
use App\Models\TransactionLine;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Variation;
use App\Models\TailoringPackageSize;

class CheckoutController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:pos/orders", "permissions:pos/payment"])->only(["__invoke"]);
    } // end of __construct

    public function __invoke(Request $request)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric",
            "customer_id" => "nullable|numeric:exists:contacts,id",
            "discount_type" => "nullable|string:in:fixed,percentage",
            "discount_amount" => "nullable|numeric",
            "notes" => "nullable|string",

            // for transaction lines
            "cart" => "required|array",
            "cart.*.product_id" => "required|numeric:exists:products,id",
            "cart.*.variation_id" => "nullable|numeric:exists:variations,id",
            "cart.*.qty" => "required|numeric",
            "cart.*.note" => "nullable|string",
            // if it is tailoring package then we need array of sizes
            "cart.*.sizes" => "nullable|array",
            "cart.*.sizes.*.id" => "required|numeric|exists:tailoring_sizes,id",
            "cart.*.sizes.*.value" => "required|numeric",
            "cart.*.fabric_id" => "nullable|numeric:exists:products,id",
            "cart.*._frm_name" => "nullable|string",
            "cart.*.extras" => "nullable|array",
            "cart.*.extras.*.id" => "required|numeric|exists:tailoring_extra,id",
            "cart.*.extras.*.value" => "required|string",

            // for taxes
            // required if tax_amount > 0
            "tax_type" => "required_with:tax_amount|string:in:fixed,percentage",
            "tax_amount" => "required|numeric",

            // for payment
            "payment" => "required|array",
            "payment.*.payment_id" => "required|numeric",
            "payment.*.amount" => "required|numeric",

            "related_invoice_id" => "nullable|numeric:exists:transactions,id",
        ]);

        if ($validation) {
            return $validation;
        }

        $total_price = 0;
        $listLinesProduct = [];
        if ($request->cart) {
            foreach ($request->cart as $cart) {
                $product = Product::find($cart['product_id']);
                if (!$product) {
                    return customResponse("Product not found", 404);
                }
                $productSellPrice = $product->sell_price;
                $pricingGroupFlag = false;
                $checkPricingGroup = Customer::where('id', $request->customer_id)
                    ->where('location_id', $request->location_id)
                    ->where('price_groups_id', '!=', null)
                    ->where('id', '!=', 1)
                    ->first();
                if ($checkPricingGroup) {
                    $pricingGroupId = $checkPricingGroup->price_groups_id;
                    $pricingGroup = \DB::table('product_group_price')
                        ->where('price_group_id', $pricingGroupId)
                        ->where('product_id', $product->id);
                        if ($product->type == "variable" || isset($cart['variation_id'])) {
                            $pricingGroup->where('variant_id', $cart['variation_id']);
                        }
                    $pricingGroup = $pricingGroup->first();
                    if ($pricingGroup) {
                        if (isset($pricingGroup->variation_id) && $pricingGroup->variant_id != null) {
                            $variation = Variation::find($pricingGroup->variant_id);
                            if (!$variation) {
                                return customResponse("Variation or price not found in the group", 404);
                            }
                            $productSellPrice = $pricingGroup->price ?? $variation->price;
                        } else{
                            $productSellPrice = $pricingGroup->price;
                        }
                        $pricingGroupFlag = true;
                    } else {
                        $productSellPrice = $product->sell_price;
                        $pricingGroupFlag = false;
                    }
                }
                if ($product->type == "single") {
                    // single
                    $total_price += $productSellPrice * $cart['qty'];
                } else if ($product->type == "variable") {
                    if (!isset($cart['variation_id'])) {
                        return customResponse("Variation id is required for variable product", 400);
                    }
                    // variation
                    $variation = Variation::find($cart['variation_id']);
                    if (!$variation) {
                        return customResponse("Variation not found", 404);
                    } else {
                        if (!$pricingGroupFlag) {
                            $productSellPrice = $variation->price;
                        }
                    }
                    $total_price += $productSellPrice * $cart['qty'];
                } else {
                    $total_price += $productSellPrice * $cart['qty'];
                }
                $data = [
                    "product_id" => $cart['product_id'],
                    "qty" => $cart['qty'],
                    "price_group_id" => isset($pricingGroup) ? ($pricingGroup->price_group_id ?? 0) : 0,
                    "product_price" => $productSellPrice,
                    "variant_id" => $cart['variation_id'] ?? null,
                    "sku" => $variation->sku ?? $product->sku,
                ];
                \Log::info("checkoutData",["data" => $data, "product_price" => $productSellPrice]);
                array_push($listLinesProduct, $data);
            }
        }

        // if the customer not 1 and has a another price group for this product or this variation
        /*if ($request->customer_id != 1 && $request->cart) {
            foreach ($request->cart as $cart) {
                $product = Product::find($cart['product_id']);
                if (!$product) {
                    return customResponse("Product not found", 404);
                }
                if (isset($request->customer_id) && $request->customer_id != 0){
                    $customerId = $request->customer_id;
                } else {
                    $customerId = 1;
                }
                if ($product->type == "single") {
                    // single
                    $customer_group = Customer::with('pricingGroup.products')->find($customerId);
                    if (!$customer_group) {
                        return customResponse("Customer not found", 404);
                    }
                    if ($customer_group->pricingGroup) {
                        $customer_group_price = $customer_group->pricingGroup->products->where('id', $product->id)->first();
                        if ($customer_group_price) {
                            $total_price -= $productSellPrice * $cart['qty'];
                            $total_price += $customer_group_price->pivot->price * $cart['qty'];
                        }
                    }
                } else if ($product->type == "variable") {
                    if (!isset($cart['variation_id'])) {
                        return customResponse("Variation id is required for variable product", 400);
                    }
                    // variation
                    $variation = Variation::find($cart['variation_id']);
                    if (!$variation) {
                        return customResponse("Variation not found", 404);
                    }

                    $customer_group = Customer::with('pricingGroup.variants')->find($customerId);
                    if (!$customer_group) {
                        return customResponse("Customer not found", 404);
                    }
                    if ($customer_group->pricingGroup) {
                        $customer_group_price = $customer_group->pricingGroup->variants->where('id', $variation->id)->first();
                        if ($customer_group_price) {
                            $total_price -= $variation->price * $cart['qty'];
                            $total_price += $customer_group_price->pivot->price * $cart['qty'];
                        }
                    }
                }
            }
        }*/


        if ($request->discount_type == "fixed") {
            $total_price -= $request->discount_amount;
        } else if ($request->discount_type == "percentage") {
            $total_price -= ($request->discount_amount / 100) * $total_price;
        }

        // calc tax
        if ($request->tax_type == "fixed") {
            $tax_amount = $request->tax_amount;
        } else if ($request->tax_type == "percentage") {
            $tax_amount = ($request->tax_amount / 100) * $total_price;
        }

        if ($request->tax_type == "fixed") {
            $total_price += $request->tax_amount;
        } else if ($request->tax_type == "percentage") {
            $total_price += ($request->tax_amount / 100) * $total_price;
        }

        $relatedTransactionId = isset($request->related_invoice_id) ? $request->related_invoice_id : null;

        // sum payment amount
        $totalPaidAmount = $request->payment ? array_sum(array_column($request->payment, 'amount')) : 0;
        if ($totalPaidAmount < $total_price) {
            $paymentStatus = "partially_paid";
        } elseif ($totalPaidAmount == $total_price) {
            $paymentStatus = "paid";
        } elseif ($totalPaidAmount == 0) {
            $paymentStatus = "not_paid";
        } else {
            $paymentStatus = "paid";
        }
        $transaction = Transaction::create([
            "location_id" => $request->location_id,
            "related_transaction_id" => $relatedTransactionId,
            "type" => "sell",
            "status" => "received",
            "is_quotation" => 0,
            "payment_status" => $paymentStatus,
            "contact_id" => $request->customer_id ?? 1,
            "supplier_id" => $request->supplier_id ?? 1,
            "notes" => $request->notes,
            "tax_amount" => $tax_amount ?? $request->tax_amount,
            "total_price" => $total_price,
            "discount_type" => $request->discount_type,
            "discount_amount" => $request->discount_amount,
            "exchange_rate" => 1,
            "created_by" => auth()->user()->id,
        ]);

        foreach ($request->cart as $key => $cart) {
            $product = Product::find($cart['product_id']);

            if ($product->type == "single") {
                $variation = null;
            } else if ($product->type == "variable") {
                if (!isset($cart['variation_id'])) {
                    return customResponse("Variation id is required for variable product", 400);
                }
                $variation = Variation::find($cart['variation_id']);
            } else if ($product->type == "tailoring_package") {
                $variation = null;
            } else {
                return customResponse("Product type not found", 404);
            }

            $stocks = $product->stocks;
            $qty = 0;
            // if ($product->type == "tailoring_package") {
            //     $fabric = Product::find($cart['fabric_id']);
            //     $stocks = $fabric->stocks;
            // }
            if ($product->type != "tailoring_package") {
                foreach ($stocks as $stock) {
                    $qty += $stock->qty_received - $stock->qty_sold;
                }

                if ($qty < $cart['qty']) {
                    if(($product->sell_over_stock != 1 && $product->sell_over_stock != '001') && (  $product->is_service !=1)) {
                        return customResponse("There is not enough qty for this product or this product is out of service", 400);
                    }
                }
            }

            // update the available stock and get the updated stock id
            if ($product->type == "single") {

                $stock_id = $this->updateStock($product->id, null, $cart['qty']);
            } else if ($product->type == "variable") {
                $stock_id = $this->updateStock($product->id, $variation->id, $cart['qty']);
            } else if ($product->type == "tailoring_package") {
                // $stock_id = $this->updateStock($fabric->id, null, $cart['qty']);
                $stock_id = null;
            } else {
                $stock_id = $this->updateStock($product->id, null, $cart['qty']);
            }

            if (!$stock_id && $product->type != "tailoring_package") {
                if(($product->sell_over_stock != 1 && $product->sell_over_stock != '001') && (  $product->is_service !=1)) {
                    return customResponse("There is not enough qty for this product or this product is out of service", 400);
                }
            }

            // if there is enough qty then attach the product to the transaction
            if ($product->type == "tailoring_package") {
                $cart['tailoring_txt'] = [];
                $cart['tailoring_txt'][0] = [];
                $fabric_length = 0;
                foreach ($cart['sizes'] as $size) {
                    $order_size = TailoringPackageSize::with("packageType")->find($size['id']);
                    if (!$order_size) {
                        return customResponse("Size not found", 404);
                    }

                    $multiplied_by = $order_size->packageType->multiple_value;
                    if ($order_size->is_primary) {
                        $fabric_length = $size['value'] * $multiplied_by;
                    }
                    array_push($cart['tailoring_txt'][0], [
                        "name" => $order_size->name,
                        "value" => $size['value'],
                        "is_primary" => $order_size->is_primary
                    ]);
                }
                array_push($cart['tailoring_txt'][0], [
                    "name" => "_frm_name",
                    "value" => $cart['_frm_name'],
                ]);
                $cart['tailoring_custom'] = [
                    "fabric_length" => $fabric_length,
                    "fabric_id" => $cart['fabric_id'],
                    "multiple" => $multiplied_by,
                    "prices" => [],
                    "notes" => $cart['note'],
                    "extras" => $cart['extras'],
                    "stock_ids" => [
                        [
                            "stock_id" => 0,
                            "increased_qty" => $fabric_length
                        ]
                    ]
                ];
                $cart['tailoring_txt'] = json_encode($cart['tailoring_txt']);
                $cart['tailoring_custom'] = json_encode($cart['tailoring_custom']);

                $transaction->products()->attach($product->id, [
                    "variation_id" => $variation ? $variation->id : null,
                    "qty" => $cart['qty'],
                    "tax_amount" => $request->tax_amount,
                    "cost" => $variation ? $variation->cost : $product->cost_price,
                    "price" => $pricingGroupFlag ? $productSellPrice : ($variation ? $variation->price : $product->sell_price),
                    "status" => "pending",
                    "stock_id" => $stock_id,
                    "note" => $cart['note'],
                    "tailoring_txt" => $cart['tailoring_txt'],
                    "tailoring_custom" => $cart['tailoring_custom'],
                ]);
            } else { // to be edited as quotation
                /*$transaction->products()->attach($product->id, [
                    "variation_id" => $variation ? $variation->id : null,
                    "qty" => $cart['qty'],
                    "tax_amount" => $request->tax_amount,
                    "cost" => $variation ? $variation->cost : $product->cost_price,
                    "price" => $pricingGroupFlag ? $productSellPrice : ($variation ? $variation->price : $product->sell_price),
                    "status" => "pending",
                    "stock_id" => $stock_id,
                    "note" => $cart['note'],
                ]);*/
                TransactionLine::create([
                    "variation_id" => $listLinesProduct[$key]['variant_id'],
                    "qty" => $listLinesProduct[$key]['qty'],
                    "tax_amount" => $tax_amount ?? $request->tax_amount,
                    "cost" => $variation ? $variation->cost : $product->cost_price,
                    "price" => $listLinesProduct[$key]['product_price'],
                    "status" => "pending",
                    "stock_id" => $stock_id,
                    "product_id" => $listLinesProduct[$key]['product_id'],
                    "sku" => $listLinesProduct[$key]['sku'],
                    "transaction_id" => $transaction->id,
                    "note" => $cart['note'],
                ]);
            }
        }

        // add payment to the transaction
        foreach ($request->payment as $payment) {
            $paymentMethod = PaymentMethod::find($payment['payment_id']);
            if (!$paymentMethod) {
                return customResponse("Payment type not found", 404);
            }
            $paymentType = $paymentMethod->name;
            $transaction->payment()->create([
                "payment_type" => $paymentType,
                "amount" => $payment['amount'],
                "created_by" => auth()->user()->id,
                "notes" => $payment['note'] ?? $request->notes,
            ]);
        }

        $sales = (new ReportController)->getSalesReport($request, $request->location_id, $transaction->id, null, 'sell', true);
        $data = [
            "data" => Transaction::with(['payment'])->find($transaction->id),
            "sales" => $sales,
        ];

        return customResponse($data, 200);
    } // end of __invoke

    private function updateStock($product_id, $variation_id = null, $qty)
    {
        if ($variation_id) {
            $stocks = Variation::find($variation_id)->stocks;
            foreach ($stocks as $stock) {
                if ($stock->qty_received - $stock->qty_sold >= $qty) {
                    $stock->qty_sold += $qty;
                    $stock->save();
                    return $stock->id;
                } else {
                    $qty -= $stock->qty_received - $stock->qty_sold;
                    $stock->qty_sold = $stock->qty_received;
                    $stock->save();
                }
            }
            return false;
        }

        $stocks = Product::find($product_id)->stocks;
        foreach ($stocks as $stock) {
            if ($stock->qty_received - $stock->qty_sold >= $qty) {
                $stock->qty_sold += $qty;
                $stock->save();
                return $stock->id;
            } else {
                $qty -= $stock->qty_received - $stock->qty_sold;
                $stock->qty_sold = $stock->qty_received;
                $stock->save();
            }
        }
        return false;
    }
}
