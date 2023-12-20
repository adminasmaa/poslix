<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\TailoringPackageSize;
use App\Models\Transaction;
use App\Models\Variation;
use Illuminate\Http\Request;
use App\Models\QuotationsList;
use App\Models\QuotationsListLines;
use App\Models\QuotationProducts;
use App\Models\Product;
use App\Models\Currency;
use App\Models\Location;

class QuotationsListController extends Controller
{
    use GeneralTrait;

    public function getQuotationList(Request $request,$id)
    {
        $quotationsList = QuotationsList::query()
            ->where('id',$id)
            ->with(['payment'])
            ->with(['quotation_list_lines'])
            ->with(['customer','employee','supplier']);
//        if (isset($request->customer_id)) {
//            $quotationsList->where('customer_id', $request->customer_id);
//        }
//        if (isset($request->employ_id)) {
//            $quotationsList->where('employ_id', $request->employ_id);
//        }
//        if (isset($request->supplier_id)) {
//            $quotationsList->where('supplier_id', $request->supplier_id);
//        }
            if (isset($request->location_id)) {
            $quotationsList->where('location_id', $request->location_id);
            $currency = Location::findOrFail($request->location_id)->currency_id;
        }
        try {
            if(isset($currency)) {
                $currencies = Currency::findOrFail($currency)
                            ->select('code');
            } else {
                $currencies = Currency::all();
            }
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        $quotationsList = $quotationsList->first();
        $listLines = [];
        foreach ($quotationsList->quotation_list_lines as $key => $quotation_list_line) {
            $product = Product::where('id', $quotation_list_line->product_id)
                ->select('id', 'name', 'sku')
                ->first();
            $quotationProduct = QuotationProducts::where('quotation_id', $quotationsList->id)
                ->where('product_id', $quotation_list_line->product_id)
                ->select('quantity', 'sub_total')
                ->first();
            $variantName = Variation::where('id', $quotation_list_line->variant_id)
                ->select('name')
                ->first();
            $listLines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name . ' ' . ($variantName->name ?? ''),
                'product_sku' => $quotation_list_line->sku ?? $product->sku,
                'product_price' => $quotation_list_line->price ?? 0,
                'product_qty' => $quotation_list_line->qty,
                'variant_id' => $quotation_list_line->variant_id ?? null,
            ];
            // remove the quotation_list_lines
            unset($quotationsList->quotation_list_lines);
        }
        $quotationsList->products = $listLines;
        $data =["quotationsList" => $quotationsList , "currency" => $currencies];
        return customResponse($data, 200);
    }

    public function getQuotationsList(Request $request)
    {
        $quotationsList = QuotationsList::query()
            ->with(['payment'])
            ->with(['quotation_list_lines'])
            ->with(['customer','employee','supplier']);
//        if (isset($request->customer_id)) {
//            $quotationsList->where('customer_id', $request->customer_id);
//        }
//        if (isset($request->employ_id)) {
//            $quotationsList->where('employ_id', $request->employ_id);
//        }
//        if (isset($request->supplier_id)) {
//            $quotationsList->where('supplier_id', $request->supplier_id);
//        }
        if (isset($request->location_id)) {
            $quotationsList->where('location_id', $request->location_id);
            $currency = Location::findOrFail($request->location_id)->currency_id;
        }
        try {
            if (isset($currency) && $currency) {
                $currencies = Currency::find($currency);
            } else {
                $currencies = Currency::all();
            }
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        $quotationsList = $quotationsList->get();
        $listLines = [];
        foreach ($quotationsList as $item){
            foreach ($item->quotation_list_lines as $key => $quotation_list_line) {
                $product = Product::where('id', $quotation_list_line->product_id)
                    ->select('id', 'name', 'sku')
                    ->first();
                $quotationProduct = QuotationProducts::where('quotation_id', $item->id)
                    ->where('product_id', $quotation_list_line->product_id)
                    ->select('quantity', 'sub_total')
                    ->first();
                $variantName = Variation::where('id', $quotation_list_line->variant_id)
                    ->select('name')
                    ->first();
                $listLines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name . ' ' . ($variantName->name ?? ''),
                    'product_sku' => $quotation_list_line->sku ?? $product->sku,
                    'product_price' => $quotation_list_line->price ?? 0,
                    'product_qty' => $quotation_list_line->qty,
                    'variant_id' => $quotation_list_line->variant_id ?? null,
                ];
                // remove the quotation_list_lines
                unset($item->quotation_list_lines);
            }
            $item->products = $listLines;
            $listLines = [];
        }
        $data =["quotationsList" => $quotationsList , "currency" => $currencies];
        return customResponse($data, 200);
    }

    public function addQuotationsList(Request $request)
    {
        //$paymentId = PaymentMethod::find($request->name)->id;
        $validation = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric|exists:business_locations,id",
            "customer_id" => "numeric",
            "discount_type" => "nullable|string:in:fixed,percentage",
            "discount_amount" => "nullable|numeric",
            "notes" => "nullable|string",
            "status" => "required|string|in:waiting,accepted,canceled",
            "cart" => "required|array",
            "cart.*.product_id" => "required|numeric|exists:products,id",
            "cart.*.variation_id" => "nullable|numeric:exists:variations,id",
            "cart.*.qty" => "required|numeric",

            // if it is tailoring package then we need array of sizes
            "cart.*.sizes" => "nullable|array",
            "cart.*.sizes.*.id" => "required|numeric|exists:tailoring_sizes,id",
            "cart.*.sizes.*.value" => "nullable|numeric",
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
                        if (isset($pricingGroup->variation_id) && $pricingGroup->variation_id != null) {
                            $variation = Variation::find($pricingGroup->variation_id);
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
                \Log::info("quotationData",["data" => $data, "product_price" => $productSellPrice]);
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
        if ($request->tax_type == "fixed") {
            $total_price += $request->tax_amount;
        } else if ($request->tax_type == "percentage") {
            $total_price += ($request->tax_amount / 100) * $total_price;
        }

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

        $quotationsList = QuotationsList::create([
            "location_id" => $request->location_id,
            "customer_id" => $request->customer_id ?? 1,
            "status" => $request->status,
            "action" => $request->action ?? null,
            "employ_id" => auth()->user()->id,
            //
            "type" => "sell",
            "payment_status" => $paymentStatus,
            "supplier_id" => $request->supplier_id ?? 1,
            "notes" => $request->notes ?? null,
            "tax_amount" => $request->tax_amount,
            "total_price" => $total_price,
            "discount_type" => $request->discount_type,
            "discount_amount" => $request->discount_amount,
            "exchange_rate" => 1,
            "created_by" => auth()->user()->id,
        ]);

        foreach ($listLinesProduct as $listLineData) {

            // if there is enough qty then attach the product to the transaction
            $quotationsListLines = QuotationsListLines::create([
                "location_id" => $request->location_id,
                "header_id" => $quotationsList->id,
                "product_id" => $listLineData['product_id'],
                "qty" => $listLineData['qty'],
                "price_group_id" => $listLineData['price_group_id'],
                "price" => $listLineData['product_price'],
                "variant_id" => $listLineData['variant_id'],
                "sku" => $listLineData['sku'],
            ]);
            $quotationProducts = QuotationProducts::create([
                "product_id" => $listLineData['product_id'],
                "quotation_id" => $quotationsList->id,
                "quantity" => $listLineData['qty'],
                "sub_total" => $listLineData['product_price'],
            ]);
        }

        // add payment to the transaction
        foreach ($request->payment as $payment) {
            $paymentMethod = PaymentMethod::find($payment['payment_id']);
            if (!$paymentMethod) {
                return customResponse("Payment type not found", 404);
            }
            $paymentType = $paymentMethod->name;
            $quotationsList->payment()->create([
                "payment_type" => $paymentType,
                "amount" => $payment['amount'],
                "created_by" => auth()->user()->id,
                "notes" => $payment['note'] ?? $request->notes,
            ]);
        }

        $data = [
            "data" => $this->getQuotationList($request, $quotationsList->id)->original['result'] ?? null,
        ];
        if ($request->status == 'accepted'){
            $data['transaction'] = (new CheckoutController())->__invoke($request)->original['result']['sales'] ?? null;
        }

        return customResponse($data, 200);
    }


    function updateQuotationsList(Request $request,$id){
        $validation = $this->validationApiTrait($request->all(), [
            "customer_id" => "numeric",
            "status" => "required|string|in:waiting,accepted,canceled",
            "location_id" => "numeric|exists:business_locations,id",
            "quotationsLines" => "array",
            "quotationsLines.*.product_id" => "numeric|exists:products,id",
            "quotationsLines.*.qty" => "numeric",
            "quotationsLines.*.id" => "required|numeric|exists:quotation_list_lines,id",
        ]);
        if ($validation) {
            return $validation;
        }
        $quotationsList = QuotationsList::find($id);
        if(!$quotationsList){
            return customResponse('Quotation list not found', 404);
        }
        try{
            $quotationsListData = [
                "location_id" => $request->has('location_id') ? $request->location_id : $quotationsList->location_id,
                "customer_id" => $request->has('customer_id') ? $request->customer_id : $quotationsList->customer_id,
                "status" => $request->has('status') ? $request->status : $quotationsList->status,
                "action" => $request->action ?? null,
                "employ_id" => auth()->user()->id
            ];
            $quotationsList->update($quotationsListData);
            if ($request->has('quotationsLines')) {
                foreach ($request->quotationsLines as $quotationsLine) {
                    $quotationId = $quotationsList->id;
                    QuotationsListLines::where('header_id', $quotationId)
                        ->where('id', $quotationsLine['id'])
                        ->update([
                        "location_id" => $request->has('location_id') ? $request->location_id : $quotationsList->location_id,
                        "header_id" =>  $request->has('header_id') ? $request->header_id : $quotationsList->id,
                        "product_id" => $quotationsLine['product_id'],
                        "qty" => $quotationsLine['qty'],
                        "price_group_id" => 0,
                    ]);
                    QuotationProducts::where('quotation_id', $quotationId)->update([
                        "product_id" => $quotationsLine['product_id'],
                        "quotation_id" => $quotationsList->id,
                        "quantity" => 0,
                        "sub_total" => 0,
                    ]);
                }
            }
            // $data = ["quotationsList" => $quotationsList, "quotationsListLines" => $quotationsListLines, "quotationProducts" => $quotationProductss];
            $requestStatus = $request->status;
            if ($request->status == 'accepted'){
                // make request to checkout
                $request = new Request();
                $cart = [];
                foreach ($quotationsList->quotation_list_lines as $quotation_list_line) {
                    $cart[] = [
                        "product_id" => $quotation_list_line->product_id,
                        "qty" => $quotation_list_line->qty,
                        "note" => $quotationsList->notes,
                    ];
                }
                $payments = [];
                foreach ($quotationsList->payment as $payment) {
                    $paymentId = PaymentMethod::where('name', $payment->payment_type)
                        ->where('location_id', $quotationsList->location_id)->first()->id;
                    $payments[] = [
                        "payment_id" => $paymentId,
                        "amount" => $payment->amount,
                        "note" => $payment->notes,
                    ];
                }
                $request->replace([
                    'location_id' => $quotationsList->location_id,
                    'customer_id' => $quotationsList->customer_id,
                    'supplier_id' => $quotationsList->supplier_id,
                    'notes' => $quotationsList->notes,
                    'action' => $quotationsList->action,
                    'cart' => $cart,
                    'tax_type' => $quotationsList->tax_type,
                    'tax_amount' => $quotationsList->tax_amount,
                    'discount_type' => $quotationsList->discount_type,
                    'discount_amount' => $quotationsList->discount_amount,
                    'payment' => $payments,
                ]);
            }
            if ($requestStatus == 'accepted') {
                $transaction = (new CheckoutController())->__invoke($request)->original;
            } else {
                $transaction = null;
            }
        }catch (\Exception $e){
            return customResponse($e->getMessage(), 500);
        }
        if ($transaction){
            return customResponse('updated successfully', 200, $transaction);
        }
        return customResponse('updated successfully', 200);
    }

    function deleteQuotationsList($id){
        $quotationsList = QuotationsList::find($id);
        if(!$quotationsList){
            return customResponse('Quotation list not found', 404);
        }
        $quotationsListLines[] = QuotationsListLines::select('header_id')->where('header_id',$id);
        foreach ($quotationsListLines as $quotationsListLine) {
            QuotationsListLines::select('header_id')->where('header_id',$id)->delete();
            QuotationProducts::select('quotation_id')->where('quotation_id',$id)->delete();
        }
        $quotationsList->delete();
        return customResponse('QuotationList deleted SuccessFullt', 200);
    }

}
