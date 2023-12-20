<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\GeneralTrait;
use App\Models\Expense;
use App\Models\QuotationsListLines;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transaction;
use DB;





class LocationReportController extends Controller
{
    use GeneralTrait;

    public function report(Request $request){
        $validator = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric",
        ]);
        if ($validator) {
            return $validator;
        }
        $sales = $this->sales($request);
        $purchases = $this->purchases($request);
        $expenses = $this->expenses($request);
        $customers = $this->customers($request);
        $lastInvoices = $this->lastInvoices($request);
        $topProducts = $this->topProducts($request);
        $bottomProducts = $this->bottomProducts($request);
        $monthlySales = $this->monthlySales($request);
        $reportData = [
            "sales" => $sales,
            "purchases" => $purchases,
            "expenses" => $expenses,
            "customers" => $customers,
            "lastInvoices" => $lastInvoices,
            "topProducts" => $topProducts,
            "bottomProducts" => $bottomProducts,
            "monthlySales" => $monthlySales,
        ];

        return customResponse($reportData, 200);
    }

    private function sales(Request $request,$salesPeriod ='daily')
    {
        if($request->has('salesPeriod')){
            $salesPeriod = $request->salesPeriod;
        }
        $query = Transaction::where('location_id', $request->location_id)
        ->where('type', 'sell');
            if ($salesPeriod === 'daily') {
                $query->whereDate('created_at', today())->get();
            } elseif ($salesPeriod === 'weekly') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->get();
            } elseif ($salesPeriod === 'monthly') {
                $query->whereMonth('created_at', now()->month)->get();
            }elseif ($salesPeriod === 'yearly') {
                $query->whereYear('created_at', now()->year)->get();
            }
        $salesCount = $query->sum('total_price');
        return $salesCount;
    }

    private function purchases(Request $request,$purchasePeriod ='daily'){
        try {
            if($request->has('purchasePeriod')){
                $purchasePeriod = $request->purchasePeriod;
            }
            $query = Transaction::query()
            ->where('location_id', $request->location_id)
            ->where('type', 'purchase');
            if ($purchasePeriod === 'daily') {
                $query->whereDate('created_at', today());
            } elseif ($purchasePeriod === 'weekly') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($purchasePeriod === 'monthly') {
                $query->whereMonth('created_at', now()->month);
            }elseif ($purchasePeriod === 'yearly') {
                $query->whereYear('created_at', now()->year);
            }
            $purchaseCount = $query->sum('total_price');
        }catch(\Exception $e){
            return $e->getMessage();
        }
        return $purchaseCount;
    }

    private function expenses(Request $request, $expensesPeriod = 'daily')
    {
        try {
            if($request->has('expensesPeriod')){
                $expensesPeriod = $request->expensesPeriod;
            }
            $query = Expense::where('location_id', $request->location_id);
            if ($expensesPeriod === 'daily') {
                $query->whereDate('created_at', today());
            } elseif ($expensesPeriod === 'weekly') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($expensesPeriod === 'monthly') {
                $query->whereMonth('created_at', now()->month);
            }elseif ($expensesPeriod === 'yearly') {
                $query->whereYear('created_at', now()->year);
            }
            $expensesCount = $query->sum('amount');
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return $expensesCount;
    }

    private function customers(Request $request, $CustomerPeriod = null)
    {
        if ($request->has('CustomerPeriod')) {
            $CustomerPeriod = $request->CustomerPeriod;
        }
        $query = Customer::query()
            ->where('location_id', $request->location_id);
        if ($CustomerPeriod === 'daily') {
            $query->whereDate('created_at', today());
        } elseif ($CustomerPeriod === 'weekly') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($CustomerPeriod === 'monthly') {
            $query->whereMonth('created_at', now()->month);
        } elseif ($CustomerPeriod === 'yearly') {
            $query->whereYear('created_at', now()->year);
        }
        $customerCount = $query->count();

        return $customerCount;
    }

    private function lastInvoices(Request $request){
        try{
            $last10Invoices = Transaction::orderBy('created_at', 'desc')
            ->where('location_id', $request->location_id)
            ->where('type', 'sell')
            ->limit(10)
            ->with(['user' => function ($query) {
                $query->select('id', 'first_name','last_name');
            }])
            ->get();
        }catch(\Exception $e){
            return $e->getMessage();
        }
        return $last10Invoices;
    }

    private function topProducts(Request $request){
        try{
            $top7Products = Product::leftJoin('transactions_lines', 'transactions_lines.product_id', '=', 'products.id')
                ->select('products.id as product_id', 'products.name as product_name', DB::raw('count(transactions_lines.id) as transaction_count'))
                ->where('location_id', $request->location_id)
                ->groupBy('products.id')
                ->orderBy('transaction_count', 'desc')
                ->limit(7)
                ->get();

        }catch(\Exception $e){
            return $e->getMessage();
        }
        return $top7Products;
    }

    private function monthlySales(Request $request){
        $montlySalesMoney = Transaction::where('location_id', $request->location_id) 
        ->where('type', 'sell')
        ->whereYear('created_at', now()->year)
        ->selectRaw('MONTH(created_at) AS month, SUM(total_price) AS total')
        ->groupBy('month')
        ->get();
        return $montlySalesMoney;
    }
    private function bottomProducts(Request $request){
        try{
            $down7Products = Product::leftJoin('transactions_lines', 'transactions_lines.product_id', '=', 'products.id')
                ->select('products.id as product_id', 'products.name as product_name', DB::raw('count(transactions_lines.id) as transaction_count'))
                ->where('location_id', $request->location_id)
                ->groupBy('products.id')
                ->orderBy('transaction_count', 'asc')
                ->limit(7)
                ->get();

        }catch(\Exception $e){
            return $e->getMessage();
        }
        return $down7Products;
    }
}
