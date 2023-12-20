<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PackageType;
use App\Http\Traits\GeneralTrait;
use App\Models\TailoringExtra;

class TailoringPackageTypeController extends Controller
{
    use GeneralTrait;

    public function index($location_id)
    {
        $packageTypes = PackageType::with('sizes')
            ->where('location_id', $location_id)->get();
        return customResponse($packageTypes, 200);
    } // end of index

    public function show($id)
    {
        $packageType = PackageType::with('sizes')->find($id);
        if (!$packageType) {
            return customResponse("Package type not found", 404);
        }
        return customResponse($packageType, 200);
    } // end of show

    public function store(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "location_id" => "required|numeric|exists:business_locations,id",
            "multiple_value" => "required|numeric",
            "sizes" => "required|array",
            "sizes.*.name" => "required|string",
            "sizes.*.is_primary" => "required|boolean",
            "extras" => "nullable|array",
        ]);
        if ($validator) {
            return $validator;
        }
        if ($request->extras) {
            $extras = implode(",", $request->extras);
            $extras = $extras . ",";
        }
        $packageType = PackageType::create([
            "name" => $request->name,
            "location_id" => $request->location_id,
            "multiple_value" => $request->multiple_value,
            "created_by" => auth()->user()->id,
            "extras" => $extras ?? null,
        ]);

        foreach ($request->sizes as $size) {
            $packageType->sizes()->create([
                "name" => $size['name'],
                "is_primary" => $size['is_primary'],
            ]);
        }
        return customResponse($packageType, 200);
    } // end of store

    public function update(Request $request, $id)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "location_id" => "required|numeric|exists:business_locations,id",
            "multiple_value" => "required|numeric",
            "sizes" => "required|array",
            "sizes.*.name" => "required|string",
            "sizes.*.is_primary" => "required|boolean",
            "extras" => "nullable|array",
        ]);
        if ($validator) {
            return $validator;
        }
        $packageType = PackageType::find($id);
        if (!$packageType) {
            return customResponse("Package type not found", 404);
        }
        if ($request->extras) {
            $extras = implode(",", $request->extras);
            $extras = $extras . ",";
        }
        $packageType->update([
            "name" => $request->name,
            "location_id" => $request->location_id,
            "multiple_value" => $request->multiple_value,
            "created_by" => auth()->user()->id,
            "extras" => $extras ?? null,
        ]);

        $packageType->sizes()->delete();
        foreach ($request->sizes as $size) {
            $packageType->sizes()->create([
                "name" => $size['name'],
                "is_primary" => $size['is_primary'],
            ]);
        }
        return customResponse($packageType, 200);
    } // end of update

    public function destroy($id)
    {
        $packageType = PackageType::find($id);
        if (!$packageType) {
            return customResponse("Package type not found", 404);
        }
        $packageType->sizes()->delete();
        $packageType->delete();
        return customResponse("Package type deleted successfully", 200);
    } // end of destroy

    public function getTailoringTypeExtras($location_id)
    {
        $extras = TailoringExtra::where('location_id', $location_id)->get();
        $extras = $extras->map(function ($extra) {
            $extra->items = json_decode($extra->items);
            return $extra;
        });
        return customResponse($extras, 200);
    } // end of getPackageTypeByLocation

    public function setTailoringTypeExtra(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string|unique:tailoring_extra,name",
            "location_id" => "required|numeric|exists:business_locations,id",
            "is_required" => "required|boolean",
            "items" => "required|array",
            "items.*.name" => "required|string",
        ]);
        if ($validator) {
            return $validator;
        }
        $extra = TailoringExtra::create([
            "name" => $request->name,
            "location_id" => $request->location_id,
            "is_required" => $request->is_required,
            "items" => json_encode($request->items),
        ]);
        $extra->items = json_decode($extra->items);
        return customResponse($extra, 200);
    } // end of storeTailoringTypeExtras

    public function updateTailoringTypeExtra(Request $request, $id)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string|unique:tailoring_extra,name," . $id,
            "location_id" => "required|numeric|exists:business_locations,id",
            "is_required" => "required|boolean",
            "items" => "required|array",
            "items.*.name" => "required|string",
        ]);
        if ($validator) {
            return $validator;
        }
        $extra = TailoringExtra::find($id);
        if (!$extra) {
            return customResponse("Extra not found", 404);
        }
        $extra->update([
            "name" => $request->name,
            "location_id" => $request->location_id,
            "is_required" => $request->is_required,
            "items" => json_encode($request->items),
        ]);
        $extra->items = json_decode($extra->items);
        return customResponse($extra, 200);
    } // end of updateTailoringTypeExtras

    public function deleteTailoringTypeExtra($id)
    {
        $extra = TailoringExtra::find($id);
        if (!$extra) {
            return customResponse("Extra not found", 404);
        }
        $extra->delete();
        return customResponse("Extra deleted successfully", 200);
    } // end of destroyTailoringTypeExtras
}
