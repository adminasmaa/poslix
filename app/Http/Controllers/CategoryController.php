<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;

class CategoryController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:category/view"])->only(["getCategories", "getCategory"]);
        $this->middleware(["permissions:category/insert"])->only(["setCategories"]);
        $this->middleware(["permissions:category/edit"])->only(["updateCategory"]);
        $this->middleware(["permissions:category/delete"])->only(["deleteCategory"]);
    } // end of __construct

    public function getCategories($location_id)
    {
        if (!Location::find($location_id)) {
            return customResponse("Location not found", 404);
        }
        $categories = Category::where('location_id', $location_id)
            ->with(['products' => function ($q) {
                $q->with('variations')->with('packages');
            }])->get();
        foreach ($categories as $category) {
            foreach ($category->products as $product) {
                $product->sell_over_stock = intval($product->sell_over_stock);
                foreach ($product->packages as $package) {
                    $package->prices_json = json_decode($package->prices_json);
                }
            }
        }
        return customResponse($categories, 200);
    } // end of getCategories

    public function getCategoriesPricingGroup($location_id, $customer_id)
    {
        if (!Location::find($location_id)) {
            return customResponse("Location not found", 404);
        }
        $customer = Customer::with([
            'pricingGroup.products',
            'pricingGroup.variants'
        ])->find($customer_id);
        if (!$customer) {
            return customResponse("Customer not found", 404);
        }

        $categories = Category::where('location_id', $location_id)
            ->with(['products' => function ($q) use ($customer) {
                $q->with(['variations' => function ($q) use ($customer) {
                    $q->whereHas('pricingGroups', function ($q) use ($customer) {
                        $q->where('price_group_id', $customer->price_groups_id);
                    });
                }])->with(['packages' => function ($q) use ($customer) {
                    $q->whereHas('pricingGroups', function ($q) use ($customer) {
                        $q->where('price_group_id', $customer->price_groups_id);
                    });
                }]);
            }])->get();

        foreach ($categories as $category) {
            foreach ($category->products as $product) {
                $product->sell_over_stock = intval($product->sell_over_stock);
                foreach ($product->packages as $package) {
                    $package->prices_json = json_decode($package->prices_json);
                }
            }
        }
        
        // update each product in that category with the customer price that in the pivot between the product and the pricing group
        foreach ($customer->pricingGroup->products as $product) {
            foreach ($categories as $category) {
                foreach ($category->products as $category_product) {
                    if ($product->id == $category_product->id) {
                        $category_product->sell_price = $product->pivot->price;
                    }
                }
            }
        }

        // update each variant in that category with the customer price that in the pivot between the variant and the pricing group
        foreach ($customer->pricingGroup->variants as $variant) {
            foreach ($categories as $category) {
                foreach ($category->products as $category_product) {
                    foreach ($category_product->variations as $category_product_variant) {
                        if ($variant->id == $category_product_variant->id) {
                            $category_product_variant->price = $variant->pivot->price;
                        }
                    }
                }
            }
        }

        return customResponse($categories, 200);
    } // end of getCustomerCategories

    public function getCategory($id)
    {
        try {
            $category = Category::with(['products' => function ($q) {
                $q->with('variations')->with('packages');
            }])->find($id);
        } catch (\Exception $e) {
            return customResponse("Category not found", 404);
        }

        foreach ($category->products as $product) {
            $product->sell_over_stock = intval($product->sell_over_stock);
            foreach ($product->packages as $package) {
                $package->prices_json = json_decode($package->prices_json);
            }
        }
        if (!$category) {
            return customResponse("Category not found", 404);
        }
        return customResponse($category, 200);
    } // end of getCategory

    public function setCategories(Request $request, $location_id)
    {
        if (!Location::find($location_id)) {
            return customResponse("Location not found", 404);
        }
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "description" => "required|string",
            "parent_id" => "nullable|numeric:exists:categories,id",
        ]);
        if ($validator) {
            return $validator;
        }
        $form_data = $request->all();
        if ((!isset($form_data["parent_id"]) || $form_data["parent_id"] == null)) {
            $form_data["parent_id"] = 0;
        }
        $form_data["created_by"] = Auth::id();
        $form_data["never_tax"] = 0;
        $form_data["show_in_list"] = "on";
        $form_data["location_id"] = $location_id;

        $category = Category::create($form_data);
        return customResponse($category, 200);
    } // end of setCategories

    public function updateCategory(Request $request, $id)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "description" => "required|string",
            "parent_id" => "nullable|numeric:exists:categories,id",
        ]);
        if ($validator) {
            return $validator;
        }
        $form_data = $request->all();
        if ((!isset($form_data["parent_id"]) || $form_data["parent_id"] == null)) {
            $form_data["parent_id"] = 0;
        }
        $category = Category::find($id);
        if (!$category) {
            return customResponse("Category not found", 404);
        }
        $category->update($form_data);
        return customResponse($category, 200);
    } // end of updateCategory

    public function deleteCategory($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return customResponse("Category not found", 404);
        }
        DB::beginTransaction();
        try {
            $category->products()->delete();
            foreach ($category->childrenCategories as $child) {
                $child->products()->delete();
            }
            $category->childrenCategories()->delete();
            $category->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // return the exciption error
            return customResponse($e->getMessage(), 500);
        }
        return customResponse("Category deleted successfully", 200);
    } // end of deleteCategory
}
