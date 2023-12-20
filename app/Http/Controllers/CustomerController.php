<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\QuotationsList;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PricingGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    use GeneralTrait;

    public function getCustomers($location_id)
    {
        $location = Location::find($location_id);
        if (!$location) {
            return customResponse("Location not found", 404);
        }
        $customers = Customer::where('location_id', $location_id)->get();
        return customResponse($customers, 200);
    } // end of getCostomers

    public function getCustomer(Request $request, $customer_id)
    {
        // customer with pricingGroup.id and pricingGroup.name
        $customer = Customer::with('pricingGroup:id,name,is_active')
        ->find($customer_id);
        if (!$customer) {
            return customResponse("Customer not found", 404);
        }
        // remove key price_groups_id from $customer
        $customer = $customer->makeHidden('price_groups_id');
        $sales = (new ReportController())->getSalesReport($request, $customer->location_id, null, $customer_id);
        $quotations = QuotationsList::query()
            ->where('customer_id', $customer_id)
            ->with('quotation_list_lines.quotation_line_product')->get();
        $quotationsCount = $this->totalQuotations($customer_id);
        $earnings = $this->totalEraning($request, $customer_id);
        $invoices = $this->invoices($request, $customer_id);

        $data = [
            "profile" => $customer,
            "sales" => $sales,
            "quotations" => $quotations,
            "quotationsCount" =>$quotationsCount,
            "earnings" => $earnings,
            "invoices" => $invoices,
        ];
        return customResponse($data, 200);
    } // end of getCustomer

    public function setCustomers(Request $request, $location_id)
    {
        $location = Location::find($location_id);
        if (!$location) {
            return customResponse("Location not found", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "first_name" => "required|string",
            "last_name" => "nullable|string",
            "mobile" => "required|numeric",
            "city" => "nullable|string",
            "state" => "nullable|string",
            "country" => "nullable|string",
            "address_line_1" => "nullable|string",
            "address_line_2" => "nullable|string",
            "zip_code" => "nullable|string",
            "shipping_address" => "nullable|string",
        ]);
        if ($validator) {
            return $validator;
        }

        $pricingGroups = PricingGroup::all();
        $form_data = $request->all();
        $form_data['created_by'] = Auth::id();
        $form_data['location_id'] = $location_id;
        $form_data["type"] = "customer";
        $form_data["contact_status"] = "active";
        $form_data["price_groups_id"] = $request->price_groups_id;
        $customer = Customer::create($form_data);
        $data=["customer"=> $customer, "pricingGroup"=> $pricingGroups  ];
        return customResponse($data, 200);
    } // end of setCustomer

    public function updateCustomer(Request $request, $customer_id)
    {
        if ($customer_id == 1){
            return customResponse("You can't update this customer", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "first_name" => "required|string",
            "last_name" => "nullable|string",
            "mobile" => "required|numeric",
            "city" => "nullable|string",
            "state" => "nullable|string",
            "country" => "nullable|string",
            "address_line_1" => "nullable|string",
            "address_line_2" => "nullable|string",
            "zip_code" => "nullable|string",
            "shipping_address" => "nullable|string",
        ]);
        if ($validator) {
            return $validator;
        }

        $pricingGroups = PricingGroup::all();
        $form_data = $request->all();
        $form_data['created_by'] = Auth::id();
        $form_data["type"] = "customer";
        $form_data["contact_status"] = "active";
        $form_data["price_groups_id"] = $request->price_groups_id;
        $customer = Customer::find($customer_id);
        if (!$customer) {
            return customResponse("Customer not found", 404);
        }
        $customer->update($form_data);
        $data=["customer"=> $customer, "pricingGroup"=> $pricingGroups];

        return customResponse($customer, 200);
    } // end of updateCustomer

    public function deleteCustomer(Customer $customer)
    {
        $customer->delete();
        return customResponse("Customer deleted successfully", 200);
    } // end of deleteCustomer

    Private function totalQuotations($customer_id){
        $quotations = QuotationsList::query()
            ->where('customer_id', $customer_id)
            ->with('quotation_list_lines.quotation_line_product');
        $quotationsCount = $quotations->count();
        return $quotationsCount;
    }

    Private function totalEraning(Request $request, $customer_id){
        $earnings = Transaction::where('contact_id', $customer_id)
            // ->where('location_id', $request->location_id)
            ->where('type','sell')
            ->where('payment_status','paid')
            ->selectRaw('SUM(total_price) AS total')
            ->get();
        return $earnings;
    }


    Private function invoices(Request $request, $customer_id){
        $invoices = Transaction::where('contact_id', $customer_id)
            ->where('type','sell');
            $Paid = clone $invoices;
            $paid = $Paid->where('payment_status','paid')->count();
            $unPaid = clone $invoices;
            $unPaid = $unPaid->where('payment_status','not_paid')->count();

            $partiallyPaid = clone $invoices;
            $partiallyPaid = $partiallyPaid->where('payment_status','partially_paid')->count();
            // $draft = clone $invoices;
            // $draft = $invoices->where('status','draft')->count();
            $canceled = clone $invoices;
            $canceled = $canceled->where('status','canceled')->count();
            $duo = $unPaid + $partiallyPaid;
            $unPaidTotal = clone $invoices;
            $unPaidTotal = $unPaidTotal->where('payment_status','not_paid')->sum('total_price');
            $paidTotal = clone $invoices;
            $paidTotal = $paidTotal->where('payment_status','paid')->sum('total_price');
            $partiallyPaidTotal = clone $invoices;
            $partiallyPaidTotal = $partiallyPaidTotal->where('payment_status','partially_paid')->sum('total_price');
            // $draftTotal = clone $invoices;
            // $draftTotal = $draftTotal->where('status','draft')->sum('total_price');
            $canceledTotal = clone $invoices;
            $canceledTotal = $invoices->where('status','canceled')->sum('total_price');
        $data=[
            "paid" => $paid,
            "unPaid" => $unPaid,
            "partiallyPaid" => $partiallyPaid,
            "duo" => $duo,
            // "draft" => $draft,
            "canceled" => $canceled,
            "unpaidMoney" => $unPaidTotal,
            "paidMoney" => $paidTotal,
            "partiallyPaidMoney" => $partiallyPaidTotal,
            // "draftMoney" => $draftTotal,
            "canceledMoney" => $canceledTotal,
        ];
        return $data;
    }

    
}
