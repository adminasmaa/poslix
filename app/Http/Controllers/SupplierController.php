<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Transaction;
use App\Http\Traits\GeneralTrait;



class SupplierController extends Controller
{
    use GeneralTrait;

    /**
     * Display a listing of the resource.
     */
    public function index($location_id)
    {
        $location = Location::find($location_id);
        if(!$location){
            return customResponse("Location not found", 404);
        }
        $suppliers = Supplier::where('location_id', $location_id)
            ->with('transaction')
            ->get();
        return customResponse($suppliers, 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $location_id)
    {

        $location = Location::find($location_id);
        if (!$location) {
            return customResponse("Location not found", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "email" => "unique:suppliers|required|string",
            "phone" => "required|numeric",
            "facility_name" => "required|string",
            "tax_number" => "required|string",
            "invoice_address" => "nullable|string",
            "invoice_City" => "nullable|string",
            "invoice_Country" => "nullable|string",
            "postal_code" => "nullable|string",
        ]);

        if ($validator) {
            return $validator;
        }
        $form_data = $request->all();
        $form_data['location_id'] = $location_id;
        $supplier = Supplier::create($form_data);
        return customResponse($supplier, 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {

        $supplier = Supplier::with('transaction')
        ->find($id);
        if (!$supplier) {
            return customResponse("supplier not found", 404);
        }
        return customResponse($supplier, 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,$id)
    {
        if ($id == 1){
            return customResponse("You can't update this supplier", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "email" => "unique:suppliers|string",
            "phone" => "required|numeric",
            "facility_name" => "required|string",
            "tax_number" => "required|string",
            "invoice_address" => "nullable|string",
            "invoice_City" => "nullable|string",
            "invoice_Country" => "nullable|string",
            "postal_code" => "nullable|string",
        ]);
        if ($validator) {
            return $validator;
        }
        $form_data = $request->all();
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return customResponse("Supplier not found", 404);
        }
        $supplier->update($form_data);
        return customResponse($supplier, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return customResponse(['message' => 'Supplier not found'], 404);
        }
        $supplier->delete();
        return customResponse(['message' => 'Supplier deleted successfully'], 200);
    }
}
