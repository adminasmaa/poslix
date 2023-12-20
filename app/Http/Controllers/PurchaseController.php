<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\TransactionExpenseCategory;

class PurchaseController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:purchase/view"])->only(["getPurchases", "getPurchase"]);
        $this->middleware(["permissions:purchase/insert"])->only(["setPurchase"]);
        $this->middleware(["permissions:purchase/edit"])->only(["updatePurchase", "updatePurchasePayment"]);
        $this->middleware(["permissions:purchase/delete"])->only(["deletePurchase"]);
    } // end of __construct

    public function getPurchases($location_id)
    {
        $location = Location::find($location_id);
        if (!$location) {
            return customResponse('Location not found', 404);
        }

        $purchases = Transaction::where('location_id', $location_id)
            ->where('type', 'purchase')
            ->with(['products', 'payment', 'stocks', 'supplier'])
            ->get();

        return customResponse($purchases, 200);
    } // end of getPurchases

    public function getPurchase($purchase_id)
    {
        $purchase = Transaction::where('type', 'purchase')
            ->with(['products', 'payment', 'stocks', 'supplier'])
            ->find($purchase_id);

        $total_paid = $purchase->payment->sum('amount');
        $purchase->total_paid = $total_paid;
        if (!$purchase) {
            return customResponse('Purchase not found', 404);
        }

        return customResponse($purchase, 200);
    } // end of getPurchase

    public function setPurchase(Request $request)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric",
            "status" => "required|string:in:draft,partially_received,processing,received,cancelled",
            "payment_status" => "required|string:in:credit,partially_paid,paid,due",
            "discount_type" => "nullable|string:in:fixed,percentage",
            "discount_amount" => "nullable|numeric",
            "notes" => "nullable|string",
            "supplier_id" => "numeric|exists:suppliers,id",
            "total_paid" => "required_if:payment_status,=,partially_paid|numeric",

            // for taxes
            "tax_amount" => "nullable|numeric",
            "label" => "required_if:tax_amount,!=,null|string",
            "value" => "required_if:tax_amount,!=,null|numeric",
            "currency_code" => "required_if:tax_amount,!=,null|string",
            "currency_id" => "required_if:tax_amount,!=,null|numeric",
            "currency_rate" => "required_if:tax_amount,!=,null|numeric",
            "converted_value" => "required_if:tax_amount,!=,null|numeric",
            "enterd_value" => "required_if:tax_amount,!=,null|numeric",
            "isNew" => "required_if:tax_amount,!=,null|boolean",

            // for transaction lines
            "cart" => "required|array",
            "cart.*.product_id" => "required|numeric:exists:products,id",
            "cart.*.variation_id" => "nullable|numeric:exists:variations,id",
            "cart.*.qty" => "required|numeric",
            "cart.*.cost" => "required|numeric",
            "cart.*.price" => "required|numeric",
            "cart.*.note" => "nullable|string",

            // for payment
            "payment_type" => "required|string:in:cash,card,cheque,bank",

            // for expenses
            "currency_id" => "nullable|numeric|exists:currencies,id",
            "expense.amount" => "nullable|numeric",
            "expense.category.id" => "nullable|numeric|exists:expenses,id",
        ]);
        if ($validation) {
            return $validation;
        }

        $total_price = 0;
        foreach ($request->cart as $item) {
            $total_price += $item['qty'] * $item['cost'];
        }
        if ($request->tax_amount && $request->tax_amount != null) {
            $total_price += $request->tax_amount;
        }
        if ($request->discount_type && $request->discount_type != null) {
            if ($request->discount_type == 'fixed') {
                $total_price -= $request->discount_amount;
            } else {
                $total_price -= ($total_price * $request->discount_amount) / 100;
            }
        }
        $total_price += $request->expense['amount'] ?? 0;

        $amount = 0;
        if ($request->payment_status == 'paid') {
            $amount = $total_price;
        } else if ($request->payment_status == 'partially_paid') {
            $amount = $request->total_paid;
        }

        $transaction = Transaction::create([
            'location_id' => $request->location_id,
            'type' => 'purchase',
            'status' => $request->status,
            'is_quotation' => 0,
            'payment_status' => $request->payment_status,
            'contact_id' => 1,
            'supplier_id' => $request->supplier_id ?? 1,

            // for taxes
            'tax_amount' => $request->tax_amount ? $request->tax_amount : 0,
            'total_taxes' => $request->tax_amount ? $request->tax_amount : 0,
            'taxes' => $request->tax_amount ? json_encode([
                [
                    'label' => $request->label,
                    'value' => $request->value,
                    'currency_code' => $request->currency_code,
                    'currency_id' => $request->currency_id,
                    'currency_rate' => $request->currency_rate,
                    'converted_value' => $request->converted_value,
                    'enterd_value' => $request->enterd_value,
                    'isNew' => $request->isNew,
                ]
            ]) : null,

            'discount_type' => $request->discount_type,
            'discount_amount' => $request->discount_amount,
            'notes' => $request->notes,
            'total_price' => $total_price,
            'exchange_rate' => 1,
            'created_by' => auth()->user()->id,
            "currency_id" => $request->currency_id,
        ]); // end of create transaction
        foreach ($request->cart as $item) {
            $transaction->products()->attach($item['product_id'], [
                'variation_id' => $item['variation_id'],
                'discount_amount' => 0,
                'qty' => $item['qty'],
                'qty_returned' => 0,
                'cost_type' => 0,
                'cost' => $item['cost'],
                'price' => $item['price'],
                'note' => $item['note'],
                'tailoring_link_num' => 0
            ]); // end of attach product
            // create stock for it
            $transaction->stocks()->create([
                'product_id' => $item['product_id'],
                'transaction_lines_id' => $transaction->products()->wherePivot('product_id', $item['product_id'])->first()->pivot->id,
                'variation_id' => $item['variation_id'] ? $item['variation_id'] : null,
                'qty_received' => $item['qty'],
                'qty_sold' => 0,
                'sold_at' => 0,
                'created_by' => auth()->user()->id,
            ]); // end of create stock
        }

        $transaction->payment()->create([
            'payment_type' => $request->payment_type,
            'amount' => $amount,
            'created_by' => auth()->user()->id,
            'notes' => $request->notes,
        ]); // end of create payment

        if ($request->expense && $request->expense != null) {
            TransactionExpenseCategory::create([
                "location_id" => $request->location_id,
                'transaction_id' => $transaction->id,
                'expense_id' => $request->expense['category']['id'],
                'entered_value' => $request->expense['amount'],
                'currency_id' => $request->currency_id,
                "currency_rate" => Currency::find($request->currency_id)->exchange_rate,
                "value" => $request->expense['category']['id'],
                "name" => ExpenseCategory::find($request->expense['category']['id'])->name,
            ]);
        }

        $finalTransaction = Transaction::where('type', 'purchase')
            ->with(['products', 'payment', 'stocks', 'supplier'])
            ->find($transaction->id);
        $finalTransaction->total_paid = $finalTransaction->payment->sum('amount');
        return customResponse($finalTransaction, 200);
    } // end of setPurchase

    public function updatePurchase(Request $request, $purchase_id)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric",
            "status" => "required|string:in:draft,partially_received,processing,received,cancelled",
            "payment_status" => "required|string:in:credit,partially_paid,paid",
            "total_paid" => "required_if:payment_status,=,partially_paid|numeric",
            "discount_type" => "nullable|string:in:fixed,percentage",
            "discount_amount" => "nullable|numeric",
            "notes" => "nullable|string",

            // for taxes
            "tax_amount" => "nullable|numeric",
            "label" => "required_if:tax_amount,!=,null|string",
            "value" => "required_if:tax_amount,!=,null|numeric",
            "currency_code" => "required_if:tax_amount,!=,null|string",
            "currency_id" => "required_if:tax_amount,!=,null|numeric",
            "currency_rate" => "required_if:tax_amount,!=,null|numeric",
            "converted_value" => "required_if:tax_amount,!=,null|numeric",
            "enterd_value" => "required_if:tax_amount,!=,null|numeric",
            "isNew" => "required_if:tax_amount,!=,null|boolean",

            // for transaction lines
            "cart" => "required|array",
            "cart.*.product_id" => "required|numeric:exists:products,id",
            "cart.*.variation_id" => "nullable|numeric:exists:variations,id",
            "cart.*.qty" => "required|numeric",
            "cart.*.cost" => "required|numeric",
            "cart.*.price" => "required|numeric",
            "cart.*.note" => "nullable|string",

            // for payment
            "payment_type" => "required|string:in:cash,card,cheque,bank",

            // for expense
            "currency_id" => "nullable|numeric|exists:currencies,id",
            "expense.amount" => "nullable|numeric",
            "expense.category.id" => "nullable|numeric|exists:expenses,id",
        ]);
        if ($validation) {
            return $validation;
        }

        $purchase = Transaction::where('type', 'purchase')
            ->where('id', $purchase_id)->first();

        if (!$purchase) {
            return customResponse('Purchase not found', 404);
        }

        $total_price = 0;
        foreach ($request->cart as $item) {
            $total_price += $item['qty'] * $item['cost'];
        }

        if ($request->tax_amount && $request->tax_amount != null) {
            $total_price += $request->tax_amount;
        }

        if ($request->discount_amount && $request->discount_amount != null) {
            if ($request->discount_type == 'fixed') {
                $total_price -= $request->discount_amount;
            } else {
                $total_price -= ($total_price * $request->discount_amount) / 100;
            }
        }
        $total_price += $request->expense['amount'] ?? 0;

        $amount = 0;
        if ($request->payment_status == 'paid') {
            $amount = $total_price;
        } else if ($request->payment_status == 'partially_paid') {
            $amount = $request->total_paid;
        }

        $purchase->update([
            'location_id' => $request->location_id,
            'type' => 'purchase',
            'status' => $request->status,
            'is_quotation' => 0,
            'payment_status' => $request->payment_status,
            'contact_id' => 1,

            // for taxes
            'tax_amount' => $request->tax_amount ? $request->tax_amount : 0,
            'total_taxes' => $request->tax_amount ? $request->tax_amount : 0,
            'taxes' => $request->tax_amount ? json_encode([
                [
                    'label' => $request->label,
                    'value' => $request->value,
                    'currency_code' => $request->currency_code,
                    'currency_id' => $request->currency_id,
                    'currency_rate' => $request->currency_rate,
                    'converted_value' => $request->converted_value,
                    'enterd_value' => $request->enterd_value,
                    'isNew' => $request->isNew,
                ]
            ]) : null,

            'discount_type' => $request->discount_type,
            'discount_amount' => $request->discount_amount,
            'notes' => $request->notes,
            'total_price' => $total_price,
            'exchange_rate' => 1,
            'created_by' => auth()->user()->id,
        ]); // end of create transaction

        $purchase->products()->detach();
        $purchase->stocks()->delete();
        $purchase->payment()->delete();

        foreach ($request->cart as $item) {
            // attach product to transaction
            $transaction = $purchase->products()->attach($item['product_id'], [
                'variation_id' => $item['variation_id'],
                'discount_type' => null,
                'discount_amount' => 0,
                'qty' => $item['qty'],
                'qty_returned' => 0,
                'cost_type' => 0,
                'cost' => $item['cost'],
                'price' => $item['price'],
                'note' => $item['note'],
                'tailoring_link_num' => 0
            ]); // end of attach product

            // create stock for it
            $purchase->stocks()->create([
                'product_id' => $item['product_id'],
                'transaction_lines_id' => $purchase->products()->wherePivot('product_id', $item['product_id'])->first()->pivot->id,
                'variation_id' => $item['variation_id'] ? $item['variation_id'] : null,
                'qty_received' => $item['qty'],
                'qty_sold' => 0,
                'sold_at' => 0,
                'created_by' => auth()->user()->id,
            ]); // end of create stock
        }

        // create payment
        $purchase->payment()->create([
            'payment_type' => $request->payment_type,
            'amount' => $amount,
            'created_by' => auth()->user()->id,
            'notes' => $request->notes,
        ]); // end of create payment

        return customResponse(Transaction::with('products', 'stocks', 'payment')->find($purchase->id), 200);
    } // end of updatePurchase

    public function updatePurchasePayment(Request $request, $purchase_id)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "payment_status" => "required|string:in:credit,partially_paid,paid,due",
            "payment_type" => "required|string:in:cash,card,cheque,bank",
            "amount" => "required|numeric",
            "notes" => "nullable|string",
        ]);
        if ($validation) {
            return $validation;
        }

        $purchase = Transaction::where('type', 'purchase')
            ->where('id', $purchase_id)
            ->find($purchase_id);

        if (!$purchase) {
            return customResponse('Purchase not found', 404);
        }

        $purchase->payment()->update([
            'payment_type' => $request->payment_type,
            'amount' => $request->amount,
            'created_by' => auth()->user()->id,
            'notes' => $request->notes,
        ]); // end of create payment

        $purchase->update([
            'payment_status' => $request->payment_status,
        ]);

        if ($request->expense && $request->expense != null) {
            $purchase->expenseCategories()->update([
                "location_id" => $request->location_id,
                'expense_id' => $request->expense['category']['id'],
                'entered_value' => $request->expense['amount'],
                'currency_id' => $request->currency_id,
                "currency_rate" => Currency::find($request->currency_id)->exchange_rate,
                "value" => $request->expense['category']['id'],
                "name" => ExpenseCategory::find($request->expense['category']['id'])->name,
            ]);
        }


        return customResponse(Transaction::with('products', 'stocks', 'payment')->find($purchase->id), 200);
    } // end of updatePurchasePayment

    public function deletePurchase($purchase_id)
    {
        $purchase = Transaction::where('type', 'purchase')
            ->where('id', $purchase_id)
            ->find($purchase_id);

        if (!$purchase) {
            return customResponse('Purchase not found', 404);
        }

        $purchase->update([
            'status' => 'cancelled'
        ]);

        return customResponse('Purchase cancelled successfully', 200);
    } // end of deletePurchase

    public function completePurchase(Request $request, $purchase_id)
    {
        $transaction = Transaction::find($purchase_id);
        if (!$transaction) {
            return customResponse("Purchase not found", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "amount" => "required|min:1",
            "payment_type" => "required|string:in:cash,card,check,bank",
        ]);
        if ($validator) {
            return $validator;
        }
        $payments = Payment::where('transaction_id', $purchase_id);
        $paymentsSum = $payments->sum('amount');
        if ($paymentsSum >= $transaction->total_price) {
            $transaction->update([
                "payment_status" => "paid",
            ]);
            return customResponse("Purchase already paid", 400);
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
            $transaction->update([
                "payment_status" => "partially_paid",
            ]);
        }
        return customResponse("Payment added successfully", 200);
    }
}
