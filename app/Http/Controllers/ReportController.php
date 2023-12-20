<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Variation;
use App\Resources\SalesReportResource;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function latestRegistrationReport(Request $request, $location_id)
    {
        return $this->getRegistrationReport($request, $location_id, true);
    } // end of latestRegistrationReport

    public function getRegistrationReport(Request $request, $location_id, $latest = false)
    {
        $data = \DB::table('cash_registers')
            ->where('location_id', $location_id);
        if (isset($request->today) && $request->today) {
            $data = $data->whereDate('cash_registers.created_at', Carbon::today());
        }
        $data = $data->join('users', 'users.id', '=', 'cash_registers.user_id')
            ->select(
                'cash_registers.id as id',
                'users.first_name as first_name',
                'users.last_name as last_name',
                'cash_registers.closing_amount as hand_cash',
                'cash_registers.total_card_slips as cart',
                'cash_registers.total_cash as cash',
                'cash_registers.total_cheques as cheque',
                'cash_registers.total_bank as bank',
                'cash_registers.created_at as date',
                'cash_registers.closing_note as note',
                'cash_registers.status as status',
            );
        $rowsCount = $data->count();
        if ($latest) {
            if (isset($request->all_data) && $request->all_data) {
                $data = $data->latest('id')->get();
                $pagination = null;
            } else {
                $data = $pagination = $data->where('cash_registers.status', 'close')->latest('id')->limit(1)->paginate(10);
            }
        } else {
            if (isset($request->all_data) && $request->all_data) {
                $data = $data->latest('id')->get();
                $pagination = null;
            } else {
                $data = $pagination = $data->latest('id')->paginate(10);
            }
        }
        $totalCash = array_sum($data->pluck('cash')->toArray());
        $totalCheque = array_sum($data->pluck('cheque')->toArray());
        $totalBank = array_sum($data->pluck('bank')->toArray());
        $totalCart = array_sum($data->pluck('cart')->toArray());
        $totalHandCash = array_sum($data->pluck('hand_cash')->toArray());

        $data = [
            'total_hand_cash' => round($totalHandCash, 2),
            'total_cash' => round($totalCash, 2),
            'total_cheque' => round($totalCheque, 2),
            'total_bank' => round($totalBank, 2),
            'total_cart' => round($totalCart, 2),
            'total' => round($totalCash + $totalCheque + $totalBank + $totalCart + $totalHandCash, 2),
            'rows_count' => $rowsCount,
            'data' => $data,
            'pagination' => $pagination ?? null,
        ];
        return customResponse($data, 200);
    } // end of getRegistrationReport

    public function getSalesReport(Request $request, $location_id, $order_id = null, $customer_id = null, $transactionType = 'sell', $function = false)
    {
        $location = Location::find($location_id);
        if (!$location) {
            $currency = null;
        }else{
            $currencyId = $location->currency_id;
            $currency = \DB::table('currencies')->where('id', $currencyId)
                ->select(
                    'currencies.currency as currency_name',
                    'currencies.code as currency_code',
                    'currencies.symbol as currency_symbol',
                )->first();
        }

        $data = Transaction::where('transactions.location_id', $location_id);
        if ($customer_id) {
            $data = $data->where('transactions.contact_id', $customer_id);
        }
        if ($transactionType != 'all') {
            $data = $data->where('transactions.type', $transactionType);
        }
        if ($order_id) {
            $data = $data->where('transactions.id', $order_id);
        }
        if ($request->has('contact_mobile') && $request->contact_mobile) {
            $data = $data->where('contacts.mobile', 'like', '%' . $request->contact_mobile . '%');
        }
        // Filter by date
        if ($request->has('start_date') && $request->has('end_date')) {
            $start_date = $request->start_date;
            $end_date = $request->end_date;

            $data = $data->whereDate('transactions.created_at', '>=', $start_date)
                         ->whereDate('transactions.created_at', '<=', $end_date);
        }


        // Filter by yesterday, last 7 days, this month, last month, this year
        if ($request->has('date_range')) {
            $dateRange = $request->date_range;
            $now = now();

            if ($dateRange === 'today') {
                $data->whereDate('transactions.created_at', $now);
            } elseif ($dateRange === 'yesterday') {
                $data->whereDate('transactions.created_at', $now->subDay());
            } elseif ($dateRange === 'last_7_days') {
                $data->whereDate('transactions.created_at', '>=', $now->subDays(6));
            } elseif ($dateRange === 'this_month') {
                $data->whereMonth('transactions.created_at', $now->month)
                    ->whereYear('transactions.created_at', $now->year);
            } elseif ($dateRange === 'last_month') {
                $data->whereYear('transactions.created_at', $now->subMonth()->year)
                     ->whereMonth('transactions.created_at', $now->subMonth()->month);
            } elseif ($dateRange === 'this_year') {
                $data->whereYear('transactions.created_at', $now->year);
            }
        }
        $data = $data->join('users', 'users.id', '=', 'transactions.created_by')
            ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
            ->join('transaction_payments', 'transaction_payments.transaction_id', '=', 'transactions.id')
            ->select(
                'transactions.id as id',
                'transactions.contact_id as contact_id',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name',
                'contacts.first_name as contact_first_name',
                'contacts.last_name as contact_last_name',
                'contacts.mobile as contact_mobile',
                'transactions.total_price as sub_total',
                'transactions.discount_amount as discount',
                'transactions.tax_amount as tax',
                'transactions.created_at as date',
                'transactions.status as transaction_status',
                'transactions.payment_status as payment_status',
                'transaction_payments.payment_type as payment_method',
                'transaction_payments.amount as payment_amount',
                'transactions.type as type',
            )
            ->latest('transactions.id');
        // Filter by customer name
        if ($request->has('contact_first_name') && $request->contact_first_name) {
            $contact_first_name = $request->contact_first_name;
            $data = $data->where('contacts.first_name', 'like', '%' . $contact_first_name . '%');

            if($request->has('contact_last_name') && $request->contact_last_name){
                $contact_last_name = $request->contact_last_name;
                $data = $data->where('contacts.last_name', 'like', '%' . $contact_last_name . '%');
            }
        }
        $cloneData = clone $data;
        $sub_total = $cloneData->get()->groupBy('id')->sum('0.sub_total');
        $tax = $cloneData->get()->groupBy('id')->sum('0.tax');
        $rowsCount = $cloneData->count();
        if (isset($request->all_data) && $request->all_data) {
            $data = $data->get();
            $pagination = null;
        } else {
            $data = $pagination = $data->paginate(10);
            unset($pagination['data']);
        }

        $data = $data->map(function ($item) {
            $item->transaction_count = Payment::where('transaction_id', $item->id)->count();
            return $item;
        });
        $payed = array_sum($data->pluck('payment_amount')->toArray());

        if (is_null($order_id)) {
            $data = $data->groupBy('id');

            // get just one for each order
            foreach ($data as $key => $item) {
                $data[$key] = $item[0];
                $data[$key]->payment_amount = $item->sum('payment_amount');
                $data[$key]->payment_method = $item->pluck('payment_method')->toArray();
                $data[$key]->tax = $item->sum('tax');
            }
        }

        $due = max(($sub_total - $payed), 0);
        $subTotalWithoutTax = $sub_total - $tax;
        $total = $sub_total;
        $data = SalesReportResource::collection($data);
        if ($customer_id) {
            return $data;
        }
        $data = [
            'sub_total_without_tax' => round($subTotalWithoutTax, 2),
            'sub_total' => round($subTotalWithoutTax, 2),
            'payed' => round($payed, 2),
            'due' => round($due, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'currency' => $currency,
            'rows_count' => $rowsCount,
            'data' => $data,
            'pagination' => $pagination ?? null,
        ];

        if ($function) {
            return $data;
        }

        return customResponse($data, 200);
    } // end of getSalesReport

    public function getPurchaseReport(Request $request, $location_id)
    {
        try{

            $data = Transaction::leftJoin('transactions_lines', 'transactions.id', '=', 'transactions_lines.transaction_id')
            ->leftJoin('products', 'transactions_lines.product_id', '=', 'products.id')
            ->leftJoin('suppliers', 'transactions.supplier_id', '=', 'suppliers.id')
            ->where('transactions.location_id', $request->location_id)
            ->where('transactions.type', 'purchase')
            ->select(
                'transactions_lines.qty',
                'transactions_lines.cost',
                'transactions_lines.transaction_id',
                'products.name as product_name',
                'products.id as product_id',
                'products.sku as product_sku',
                'transactions.created_at',
                'suppliers.name as supplier_name'
            )
            ->get();
            foreach ($data as $transaction) {
                $subtotal = $transaction->qty * $transaction->cost;
                $transaction->subtotal = $subtotal;
            }


        }catch(\Exeption $e){
            return $e->getMessage();
        }
        return customResponse($data, 200);
    }

    public function getItemSalesReport(Request $request, $location_id, $order_id = null)
    {
        $currencyId = Location::find($location_id)->currency_id;
        $currency = \DB::table('currencies')->where('id', $currencyId)
            ->select(
                'currencies.currency as currency_name',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            )->first();
        $data = \DB::table('transactions_lines')
            ->where('transactions.location_id', $location_id);
        //->where('transactions.type', 'sell'); // get all types
        if ($order_id) {
            $data = $data->where('transactions.id', $order_id);
        }
        if ($request->has('contact_mobile') && $request->contact_mobile) {
            $data = $data->where('contacts.mobile', 'like', '%' . $request->contact_mobile . '%');
        }
        if ($request->has('product_id') && $request->product_id) {
            $data = $data->where('transactions_lines.product_id', $request->product_id);
        }
        if ($request->has('product_name') && $request->product_name) {
            $data = $data->where('products.name', 'like', '%' . $request->product_name . '%');
        }

        // Filter by customer name
        if ($request->has('contact_first_name') && $request->contact_first_name) {
            $contact_first_name = $request->contact_first_name;
            $data = $data->where('contacts.first_name', 'like', '%' . $contact_first_name . '%');

            if($request->has('contact_last_name') && $request->contact_last_name){
                $contact_last_name = $request->contact_last_name;
                $data = $data->where('contacts.last_name', 'like', '%' . $contact_last_name . '%');
            }
        }


        $data = $data
            ->join('transactions', 'transactions.id', '=', 'transactions_lines.transaction_id')
            ->leftJoin('products', 'products.id', '=', 'transactions_lines.product_id')
        //            ->join('variations', 'variations.id', '=', 'transactions_lines.variation_id')
            ->leftJoin('users', 'users.id', '=', 'transactions.created_by')
            ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'transactions.supplier_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->select(
                'transactions.id as order_id',
                'transactions.type as type',
                'transactions_lines.id as transactions_line_id',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name',
                'contacts.first_name as contact_first_name',
                'contacts.last_name as contact_last_name',
                'contacts.mobile as contact_mobile',
                'suppliers.name as supplier_name',
                'suppliers.phone as supplier_mobile',
                'suppliers.email as supplier_email',
        //                'variations.name as variation_name',
                'transactions_lines.qty as qty',
                'transactions_lines.price as price',
                'transactions_lines.cost as cost',
                'transactions_lines.tax_amount as tax',
                'transactions.created_at as date',
                'transactions.status as status',
                'transactions.type as type',
                'transactions_lines.product_id as product_id',
                'categories.name as category_name', // Select category name
                'brands.name as brand_name', // Select brand name
                'products.cost_price as product_cost'
            )->latest('transactions.id');

        // Filter by purchase date
        if ($request->has('purchase_date')) {
            $purchase_date = $request->purchase_date;
            $data = $data->whereDate('transactions.created_at', $purchase_date);
        }


        // Filter by supplier name
        if($request->has('supplier_name')){
            $supplier_name = $request->supplier_name;
            $data = $data->where('suppliers.name', 'like', '%' . $supplier_name . '%');
        }
        if (isset($request->all_data) && $request->all_data) {
            $data = $data->get();
            $pagination = null;
        } else {
            $data = $pagination = $data->paginate(10);
            unset($pagination['data']);
        }
        foreach ($data as $item) {
            $item->product = Product::where('id', $item->product_id)->with('variations')
                ->with('packages')
                ->with('category')
                ->with('brand')
                ->first();
            $item->products = [];
        }
        foreach ($data as $item) {
            if (isset($item->product)) {
                $item->product->product_qty = $item->qty;
            }
        }
        // group data by order_id
        $data = $data->groupBy('order_id');

        foreach ($data as $key => $item) {
            foreach ($item as $key2 => $item2) {
                if ($key2 != 0) {
                    $item[0]->qty += $item2->qty;
                    $item[0]->price += $item2->price;
                    $item[0]->cost += $item2->cost;
                    if ($order_id){
                        $item[0]->tax = $item2->tax;
                    } else {
                        $item[0]->tax += $item2->tax;
                    }
                }
            }
        }


        foreach ($data as $key => $item) {
            foreach ($item as $key2 => $item2) {
                // remove product key
                unset($data[$key][$key2]->product);
                unset($data[$key][$key2]->product_id);
                unset($data[$key][$key2]->transactions_line_id);
                if ($key2 != 0) {
                    unset($data[$key][$key2]);
                }
            }
            // take first only
            $data[$key] = $data[$key][0];

            // array values products
            $products = array_values($data[$key]->products);
            $listLines = [];
            $transactionLines = TransactionLine::where('transaction_id', $data[$key]->order_id)
                ->get();
            foreach ($transactionLines as $transactionLine) {
                $product = Product::where('id', $transactionLine->product_id)
                    ->with('category', 'brand')
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
                    'category_id' => $product->category->id ?? '',
                    'category_name' => $product->category->name ?? '',
                    'brand_id' => $product->brand->id ?? '',
                    'brand_name' => $product->brand->name ?? '',
                ];
            }
            $data[$key]->products = $listLines;
        }


        $sub_total = array_sum($data->pluck('price')->toArray());
        if ($order_id) {
            $tax = $data->first()->tax ?? 0;
        } else {
            $tax = array_sum($data->pluck('tax')->toArray());
        }
        $cost = array_sum($data->pluck('cost')->toArray());
        $total = $sub_total + $tax;

        $data = array_values($data->toArray());

        $data = [
            'cost' => round($cost, 2), // 'cost' => '0.00
            'sub_total' => round($sub_total, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'currency' => $currency,
            'rows_count' => count($data),
            'data' => $data,
            'pagination' => $pagination ?? null,
        ];



        return customResponse($data, 200);
    } // end of getItemSalesReport

    public function getItemStockReport(Request $request)
    {

        $data = \DB::table('products');//->where('products.status', '=', 'active')->where('is_service', '=', '0');

        if ($request->has('location_id') && $request->location_id) {
            $data = $data->where('products.location_id', '=', $request->location_id);
        }
        if ($request->has('category_id') && $request->category_id) {
            $data = $data->where('products.category_id', '=', $request->category_id);
        }

        if ($request->has('brand_id') && $request->brand_id) {
            $data = $data->where('brands.id', '=', $request->brand_id);
        }
        if ($request->has('sub_category') && $request->sub_category) {
            $data = $data->where('products.sub_category_id', '=', $request->sub_category);
        }
        if ($request->has('unit_name') && $request->unit_name) {
            $data = $data->where('units.name', 'like', $request->unit_name);
        }

        $data = $data
            //->join('transactions', 'transactions.id', '=', 'transactions_lines.transaction_id')
            //->join('stock', 'products.id', '=', 'stock.product_id')
            ->leftJoin('business_locations', 'business_locations.id', '=', 'products.location_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id');
        $data = $data->select(
            'products.id as id',
            'products.sku as sku',
            'products.name as product_name',
            'products.sub_category_id as sub_category',
            'products.cost_price',
            'products.sell_price',
            'units.name as unit_name',
            //'stock.qty_received as receive_qty',
            //'stock.qty_sold as sold_qty',
            'brands.id as brand_id',
            'brands.name as brand_name',
            'categories.id as category_id',
            'categories.name as category_name',
            'business_locations.name as location_name',
        );


        $rowsCount = $data->count();
        if (isset($request->all_data) && $request->all_data) {
            $data = $data->get();
            $pagination = null;
        } else {
            $data = $pagination = $data->paginate(10);
            unset($pagination['data']);
        }
        foreach ($data as $item) {
            $item->receive_qty = $receive_qty = \DB::table('stock')->where('product_id', $item->id)->sum('qty_received');
            $item->sold_qty = $sold_qty = \DB::table('stock')->where('product_id', $item->id)->sum('qty_sold');
            $available_qty = $receive_qty - $sold_qty;
            $item->available_qty = number_format($available_qty, 2);
            $item->total_sold_price = round(($sold_qty * $item->sell_price), 2);
        }

        $data['rows_count'] = $rowsCount;

        return customResponse($data, 200);
    } // end of getItemSalesReport


// Item report


    public function getItemItemReport(Request $request, $location_id, $order_id = null)
    {

        $data = \DB::table('products')->where('products.status', '=', 'active')->where('is_service', '=', '0');

        if ($request->has('location_id') && $request->location_id) {
            $data = $data->where('products.location_id', '=', $request->location_id);
        }
        if ($request->has('category_id') && $request->category_id) {
            $data = $data->where('products.category_id', '=', $request->category_id);
        }

        if ($request->has('brand_id') && $request->brand_id) {
            $data = $data->where('brands.id', '=', $request->brand_id);
        }
        if ($request->has('sub_category') && $request->sub_category) {
            $data = $data->where('products.sub_category_id', '=', $request->sub_category);
        }
        if ($request->has('unit_name') && $request->unit_name) {
            $data = $data->where('units.name', 'like', $request->unit_name);
        }

        $data = $data
            //->join('transactions', 'transactions.id', '=', 'transactions_lines.transaction_id')
            ->join('stock', 'products.id', '=', 'stock.product_id')
            ->join('business_locations', 'business_locations.id', '=', 'products.location_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->join('transactions_lines', 'transactions_lines.product_id', '=', 'products.id')
            ->join('transactions', 'transactions.id', '=', 'transactions_lines.transaction_id')
            ->join('contacts', 'contacts.id', '=', 'transactions.contact_id')
            ->join('categories', 'categories.id', '=', 'products.category_id');
        $data = $data->select(


            'products.sku as sku',
            'products.name as product_name',
            'categories.name as category_name',
            'products.product_description as product_description',
            'products.sub_category_id as sub_category',
            'products.cost_price',
            'products.sell_price',
            'units.name as unit_name',
            'stock.qty_received as receive_qty',
            'stock.qty_sold as sold_qty',
            'brands.name as brand_name',
            'brands.id as brand_id',
            'transactions.type',
            'contacts.first_name',
            'contacts.last_name',
            'transactions.invoice_no',
            'transactions.invoice_no',
            'business_locations.name as location_name',
        );

        $rowsCount = $data->count();
        if (isset($request->all_data) && $request->all_data) {
            $data = $data->get();
            $pagination = null;
        } else {
            $data = $pagination = $data->paginate(10);
            unset($pagination['data']);
        }


        $data['rows_count'] = $rowsCount;
        return customResponse($data, 200);

    } // end of getItemSalesReport
}
