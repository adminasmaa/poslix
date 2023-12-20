<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExpenseCategory;
use App\Http\Traits\GeneralTrait;
use App\Models\Location;

class ExpenseCategoryController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:expanses/view"])->only(["getCategories", "getCategory"]);
        $this->middleware(["permissions:expanses/insert"])->only(["setCategory"]);
        $this->middleware(["permissions:expanses/insert"])->only(["updateCategory"]);
        $this->middleware(["permissions:expanses/delete"])->only(["deleteCategory"]);
    } // end of __construct

    public function getCategories($location_id)
    {
        try {
            $categories = ExpenseCategory::where('location_id', $location_id)->get();
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($categories);
    } // end of getCategories

    public function getCategory($id)
    {
        try {
            $category = ExpenseCategory::find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$category) {
            return customResponse("Category not found", 404);
        }
        return customResponse($category);
    } // end of getCategory

    public function setCategory(Request $request, $location_id)
    {
        $valdator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($valdator) {
            return $valdator;
        }
        if (!$location = Location::find($location_id)) {
            return customResponse("Location not found", 404);
        }
        try {
            $category = ExpenseCategory::create([
                'name' => $request->name,
                'location_id' => $request->location_id,
            ]);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($category, 200);
    } // end of addCategory

    public function updateCategory(Request $request, $id)
    {
        $valdator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($valdator) {
            return $valdator;
        }
        try {
            $category = ExpenseCategory::find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$category) {
            return customResponse("Category not found", 404);
        }
        try {
            $category->update([
                'name' => $request->name,
                'location_id' => $request->location_id,
            ]);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($category);
    } // end of updateCategory

    public function deleteCategory($id)
    {
        try {
            $category = ExpenseCategory::find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$category) {
            return customResponse("Category not found", 404);
        }
        try {
            $category->delete();
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse("Category deleted successfully");
    } // end of deleteCategory
}
