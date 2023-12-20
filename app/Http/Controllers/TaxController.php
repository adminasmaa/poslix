<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Tax;
use Illuminate\Support\Facades\DB;

class TaxController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:taxes/view"])->only(["getTaxes", "getTax"]);
        $this->middleware(["permissions:taxes/insert"])->only(["setTax"]);
        $this->middleware(["permissions:taxes/insert"])->only(["updateTax"]);
        $this->middleware(["permissions:taxes/delete"])->only(["deleteTax"]);
    }

    public function getTaxes($location_id)
    {
        $location = Location::find($location_id);
        if (!$location) {
            return customResponse("Location not found", 404);
        }

        $taxes = Tax::where("location_id", $location_id)->with("taxGroup")->get();
        return customResponse(["taxes" => $taxes], 200);
    } // end of getTaxes

    public function getTax($tax_id)
    {
        $tax = Tax::with("taxGroup")->find($tax_id);
        if (!$tax) {
            return customResponse("Tax not found", 404);
        }

        return customResponse(["tax" => $tax], 200);
    } // end of getTax

    public function setTax(Request $request, $location_id)
    {
        $location = Location::find($location_id);
        if (!$location) {
            return customResponse("Location not found", 404);
        }

        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "amount" => "required|numeric",
            "type" => "required|string|in:percentage,fixed",
            "is_primary" => "required|boolean",
            "tax_type" => "required|string|in:primary,group,excise,service",

            "tax_ids" => "required_if:tax_type,group|array",
        ]);

        if ($validator) {
            return customResponse($validator, 422);
        }
        // check if is_primary is true and there is another tax with is_primary = true
        if ($request->is_primary) {
            $allTaxes = Tax::where("location_id", $location_id)->where("tax_type", $request->tax_type);
            $allTaxes->update([
                "is_primary" => 0,
            ]);
        }

        $tax = Tax::create([
            "location_id" => $location_id,
            "name" => $request->name,
            "amount" => $request->amount,
            "is_tax_group" => $request->tax_type == "group" ? 1 : 0,
            "for_tax_group" => 0,
            "created_by" => auth()->user()->id,
            "for_tax_inclusive" => 0,
            "for_tax_exclusive" => 0,
            "is_inc_or_exc" => "inc",
            "type" => $request->type,
            "is_primary" => $request->is_primary,
            "tax_type" => $request->tax_type,
        ]);

        $parent_tax_id = $tax->id;
        if ($request->tax_type == "group") {
            $taxes = Tax::where("location_id", $location_id)->whereIn("id", $request->tax_ids)->get();
            if ($taxes->count() != count($request->tax_ids)) {
                return customResponse("One or more taxes not found", 404);
            }

            foreach ($taxes as $tax) {
                DB::table("tax_group")->insert([
                    "parent_id" => $parent_tax_id,
                    "tax_id" => $tax->id,
                    "location_id" => $location_id,
                ]);
            }
        }

        return customResponse(["tax" => Tax::with("taxGroup")->find($parent_tax_id)], 200);
    } // end of createTax

    public function updateTax(Request $request, $tax_id)
    {
        $tax = Tax::find($tax_id);
        if (!$tax) {
            return customResponse("Tax not found", 404);
        }

        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "amount" => "required|numeric",
            "type" => "required|string|in:percentage,fixed",
            "is_primary" => "required|boolean",
            "tax_type" => "required|string|in:primary,group,excise,service",

            "tax_ids" => "required_if:tax_type,group|array",
        ]);

        if ($validator) {
            return customResponse($validator, 422);
        }

        if ($request->is_primary) {
            $allTaxes = Tax::where("location_id", $tax->location_id)->where("tax_type", $request->tax_type);
            $allTaxes->update([
                "is_primary" => 0,
            ]);
        }

        $tax->update([
            "name" => $request->name,
            "amount" => $request->amount,
            "is_tax_group" => $request->tax_type == "group" ? 1 : 0,
            "for_tax_group" => 0,
            "created_by" => auth()->user()->id,
            "for_tax_inclusive" => 0,
            "for_tax_exclusive" => 0,
            "is_inc_or_exc" => "inc",
            "type" => $request->type,
            "is_primary" => $request->is_primary,
            "tax_type" => $request->tax_type,
        ]);

        $parent_tax_id = $tax->id;
        DB::table("tax_group")->where("parent_id", $parent_tax_id)->delete();
        if ($request->tax_type == "group") {
            $taxes = Tax::where("location_id", $tax->location_id)->whereIn("id", $request->tax_ids)->get();
            if ($taxes->count() != count($request->tax_ids)) {
                return customResponse("One or more taxes not found", 404);
            }

            foreach ($taxes as $tax) {
                DB::table("tax_group")->insert([
                    "parent_id" => $parent_tax_id,
                    "tax_id" => $tax->id,
                    "location_id" => $tax->location_id,
                ]);
            }
        }

        return customResponse(["tax" => Tax::with("taxGroup")->find($parent_tax_id)], 200);
    } // end of updateTax

    public function deleteTax($tax_id)
    {
        $tax = Tax::find($tax_id);
        if (!$tax) {
            return customResponse("Tax not found", 404);
        }
        DB::table("tax_group")->where("parent_id", $tax_id)->delete();
        $tax->delete();
        return customResponse(["message" => "Tax deleted successfully"], 200);
    } // end of deleteTax

}
