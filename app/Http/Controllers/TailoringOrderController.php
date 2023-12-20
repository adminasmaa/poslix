<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Location;
use Illuminate\Http\Request;
use App\Models\Transaction;

class TailoringOrderController extends Controller
{
    use GeneralTrait;

    public function getOrders($location_id)
    {
        if (Location::find($location_id) == null) {
            return customResponse("Location not found", 404);
        }
        $transactions = Transaction::select("id")->where(['location_id' => $location_id, 'type' => 'sell'])
            ->whereHas('products', function ($query) {
                $query->where('tailoring_txt', '!=', null);
            })
            ->with([
                'products' => function ($query) {
                    $query->with('tailoringTypes');
                }
            ])->latest()->paginate(10);

        $transactions->each(function ($transaction) {
            $transaction->products->makeHidden([
                "pivot",
                "name",
                "business_id",
                "location_id",
                "type",
                "is_tailoring",
                "is_service",
                "is_fabric",
                "subproductname",
                "unit_id",
                "brand_id",
                "category_id",
                "sub_category_id",
                "tax",
                "never_tax",
                "alert_quantity",
                "sku",
                "barcode_type",
                "image",
                "product_description",
                "created_by",
                "is_disabled",
                "sell_price",
                "cost_price",
                "sell_over_stock",
                "qty_over_sold",
                "created_at",
                "updated_at",
                "is_selling_multi_price",
                "is_fifo",
                "stock"
            ]);
            $transaction->products = $transaction->products->each(function ($product) {
                $product->tailoring_txt = json_decode($product->pivot->tailoring_txt)[0];
                $product->tailoring_custom = json_decode($product->pivot->tailoring_custom);
                $product->status = $product->pivot->status;
            });

            $transaction->product = $transaction->products->first();
            $transaction->product->tailoringType = $transaction->product->tailoringTypes->first();
            $transaction->product->makeHidden('tailoringTypes');
            $transaction->product->tailoringType->makeHidden([
                "id",
                "location_id",
                "multiple_value",
                "created_by",
                "created_at",
                "extras",
                "pivot"
            ]);
            $transaction->makeHidden('products');
        });
        return customResponse($transactions, 200);
    } // end of getOrders

    public function updateOrderStatus(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
            'product_id' => 'required|exists:products,id',
            'status' => 'required|in:pending,processing,complete'
        ]);
        if ($validator) {
            return customResponse($validator, 400);
        }

        $transaction = Transaction::find($request->transaction_id);
        $transaction->products()->updateExistingPivot($request->product_id, ['status' => $request->status]);
        return customResponse('Order status updated successfully', 200);
    }
}
