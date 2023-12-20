<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\TransactionExpenseCategory;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Stock;

class TransferController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:purchase/view"])->only(["getPurchases", "getPurchase"]);
        $this->middleware(["permissions:purchase/insert"])->only(["setPurchase"]);
        $this->middleware(["permissions:purchase/edit"])->only(["updatePurchase", "updatePurchasePayment"]);
        $this->middleware(["permissions:purchase/delete"])->only(["deletePurchase"]);
    } // end of __construct

    public function getTransfers($location_id)
    {
        $location = Location::find($location_id);
        if (!$location) {
            return customResponse('Location not found', 404);
        }

        $transfers = Transaction::where(function ($query) use ($location_id) {
            $query->where('location_id', $location_id)
                ->orWhere('transferred_location_id', $location_id);
        })
            ->where('type', 'transfer')
            ->with(['products', 'stocks'])
            ->latest()->get();

        foreach ($transfers as $transfer) {
            // attach the location name
            $transfer->location_from_name = Location::find($transfer->location_id)->name;
            $transfer->location_to_name = Location::find($transfer->transferred_location_id)->name;
            if ($transfer->location_id == $location_id) {
                $transfer->is_sent = true;
                $transfer->is_received = false;
            } else {
                $transfer->is_received = true;
                $transfer->is_sent = false;
            }
        }

        return customResponse($transfers, 200);
    } // end of getTransfers

    public function getTransfer($transfer_id)
    {
        $transfer = Transaction::where('type', 'transfer')
            ->with(['products', 'stocks'])
            ->find($transfer_id);

        if (!$transfer) {
            return customResponse('Transfer not found', 404);
        }

        if ($transfer->location_id == $transfer->transferred_location_id) {
            $transfer->is_sent = true;
            $transfer->is_received = true;
        } else {
            if ($transfer->location_id == auth()->user()->location_id) {
                $transfer->is_sent = true;
                $transfer->is_received = false;
            } else {
                $transfer->is_received = true;
                $transfer->is_sent = false;
            }
        }

        return customResponse($transfer, 200);
    } // end of getTransfer

    public function setTransfer(Request $request)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric",
            "transferred_location_id" => "required|numeric",
            "ref_no" => "required|numeric|unique:transactions,ref_no",
            "status" => "required|string:in:draft,partially_received,processing,received,cancelled",
            "notes" => "nullable|string",

            // for transaction lines
            "cart" => "required|array",
            "cart.*.product_id" => "required|numeric:exists:products,id",
            "cart.*.transferred_product_id" => "nullable|numeric:exists:products,id",
            "cart.*.variation_id" => "nullable|numeric:exists:variations,id",
            "cart.*.transferred_variation_id" => "nullable|numeric:exists:variations,id",
            "cart.*.qty" => "required|numeric",
            "cart.*.cost" => "required|numeric",
            "cart.*.price" => "required|numeric",
            "cart.*.note" => "nullable|string",

            // for expenses
            "currency_id" => "nullable|numeric|exists:currencies,id",
            "expense.amount" => "nullable|numeric",
            "expense.category.id" => "nullable|numeric|exists:expenses,id",
        ]);
        if ($validation) {
            return $validation;
        }
        // check if that location has the product
        foreach ($request->cart as $item) {
            if (isset($item['variation_id']) && $item['variation_id'] != null && isset($item['transferred_variation_id']) && $item['transferred_variation_id'] != null) {
                $product = Product::find($item['product_id'])->variations()->where('id', $item['variation_id'])->where('location_id', $request->location_id)->first();
                $transferred_product = Product::find($item['transferred_product_id'])->variations()->where('id', $item['transferred_variation_id'])->where('location_id', $request->transferred_location_id)->first();
            } else {
                $product = Product::find($item['product_id'])->where('location_id', $request->location_id)->first();
                $transferred_product = Product::find($item['transferred_product_id'])->where('location_id', $request->transferred_location_id)->first();
            }
            if (!$product) {
                return customResponse('Product not found in the current location', 404);
            }
            if (!$transferred_product) {
                return customResponse('Transferred product not found in the transferred location', 404);
            }
        }

        $transaction = Transaction::create([
            'location_id' => $request->location_id,
            'transferred_location_id' => $request->transferred_location_id,
            'type' => 'transfer',
            'status' => $request->status,
            'is_quotation' => 0,
            'contact_id' => 1,
            'supplier_id' => $request->supplier_id ?? 1,
            "ref_no" => $request->ref_no,
            'notes' => $request->notes,
            'exchange_rate' => 1,
            'created_by' => auth()->user()->id,
            "currency_id" => $request->currency_id,
        ]); // end of create transaction

        foreach ($request->cart as $item) {
            // reduce the product stocks from the current location
            if (isset($item['variation_id']) && $item['variation_id'] != null) {
                $stocks = Stock::where('product_id', $item['product_id'])
                    ->where('variation_id', $item['variation_id'])
                    ->where('qty_received', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();
            } else {
                $stocks = Stock::where('product_id', $item['product_id'])
                    ->where('qty_received', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();
            }
            if (!$stocks) {
                return customResponse('It is out of your stock', 404);
            }

            $qty = $item['qty'];
            foreach ($stocks as $stock) {
                if ($qty > 0) {
                    if (($stock->qty_received - $stock->qty_sold) >= $qty) {
                        $qty = 0;
                    } else {
                        $qty -= ($stock->qty_received - $stock->qty_sold);
                    }
                }
            }
            // check if the qty is greater than the available qty
            if ($qty > 0) {
                return customResponse('It is out of your stock', 404);
            }

            $qty = $item['qty'];
            foreach ($stocks as $stock) {
                if ($qty > 0) {
                    if (($stock->qty_received - $stock->qty_sold) >= $qty) {
                        $stock->update([
                            'qty_sold' => $stock->qty_sold + $qty,
                            'sold_at' => $transaction->id,
                        ]);
                        $qty = 0;
                    } else {
                        $qty -= ($stock->qty_received - $stock->qty_sold);
                        $stock->update([
                            'qty_sold' => $stock->qty_received,
                            'sold_at' => $transaction->id,
                        ]);
                    }
                }
            }
            // attach product to transaction
            $transaction->products()->attach($item['transferred_product_id'], [
                'variation_id' => isset($item['transferred_variation_id']) && $item['transferred_variation_id'] != null ? $item['transferred_variation_id'] : null,
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
                'product_id' => $item['transferred_product_id'],
                'transaction_lines_id' => $transaction->products()->wherePivot('product_id', $item['transferred_product_id'])->first()->pivot->id,
                'variation_id' => isset($item['transferred_variation_id']) && $item['transferred_variation_id'] != null ? $item['transferred_variation_id'] : null,
                'qty_received' => $item['qty'],
                'qty_sold' => 0,
                'sold_at' => 0,
                'created_by' => auth()->user()->id,
            ]); // end of create stock
        }

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

        return customResponse(Transaction::with(['products', 'stocks'])->find($transaction->id), 200);
    } // end of setTransfer

    public function deleteTransfer($transfer_id)
    {
        $transfer = Transaction::where('type', 'transfer')
            ->where('id', $transfer_id)
            ->find($transfer_id);

        if (!$transfer) {
            return customResponse('Transfer not found', 404);
        }

        $transfer->update([
            'status' => 'cancelled'
        ]);

        return customResponse('Transfer cancelled successfully', 200);
    } // end of deleteTransfer

    public function receivedTransfer($transfer_id) {
        $transfer = Transaction::where('type', 'transfer')
            ->where('id', $transfer_id)
            ->find($transfer_id);

        if (!$transfer) {
            return customResponse('Transfer not found', 404);
        }

        $transfer->update([
            'status' => 'received'
        ]);

        return customResponse('Transfer received successfully', 200);
    } // end of receivedTransfer
}
