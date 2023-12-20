<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\GeneralTrait;
use App\Models\Brand;
use Illuminate\Support\Facades\Auth;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;

class BrandController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        // pass a parameter to the middleware
        $this->middleware(["permissions:category/view"])->only(["getBrands", "getBrand"]);
        $this->middleware(["permissions:category/insert"])->only(["setBrands"]);
        $this->middleware(["permissions:category/edit"])->only(["updateBrand"]);
        $this->middleware(["permissions:category/delete"])->only(["deleteBrand"]);
    } // end of __construct

    public function getBrands($location_id)
    {
        try {
            $brands = Brand::where('location_id', $location_id)->with(["products" => function ($q) {
                $q->with("variations")->with("packages")->with("stocks");
            }])->get();
            
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($brands);
    } // end of getBrands

    public function getBrandsPricingGroup($location_id, $customer_id)
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

        $brands = Brand::where('location_id', $location_id)
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

        foreach ($brands as $brand) {
            foreach ($brand->products as $product) {
                foreach ($product->packages as $package) {
                    $package->prices_json = json_decode($package->prices_json);
                }
            }
        }

        // update each product in that brand with the customer price that in the pivot between the product and the pricing group
        foreach ($customer->pricingGroup->products as $product) {
            foreach ($brands as $brand) {
                foreach ($brand->products as $brand_product) {
                    if ($product->id == $brand_product->id) {
                        $brand_product->sell_price = $product->pivot->price;
                    }
                }
            }
        }

        // update each variant in that brand with the customer price that in the pivot between the variant and the pricing group
        foreach ($customer->pricingGroup->variants as $variant) {
            foreach ($brands as $brand) {
                foreach ($brand->products as $brand_product) {
                    foreach ($brand_product->variations as $brand_product_variant) {
                        if ($variant->id == $brand_product_variant->id) {
                            $brand_product_variant->price = $variant->pivot->price;
                        }
                    }
                }
            }
        }

        return customResponse($brands, 200);
    } // end of getCustomerBrandsPricingGroup

    public function getBrand($id)
    {
        try {
            $brand = Brand::with("products")->find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$brand) {
            return customResponse("Brand not found", 404);
        }
        return customResponse($brand);
    } // end of getBrand

    public function setBrands(Request $request, $location_id)
    {
        $valdator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        if (!Location::find($location_id)) {
            return customResponse("Location not found", 404);
        }
        if ($valdator) {
            return $valdator;
        }
        try {
            $brand = Brand::create([
                'name' => $request->name,
                'description' => $request->description,
                'location_id' => $location_id,
                'created_by' => Auth::id(),
                "never_tax" => 0,
            ]);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($brand, 201);
    } // end of setBrands

    public function updateBrand(Request $request, $id)
    {
        $valdator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        if ($valdator) {
            return $valdator;
        }
        try {
            $brand = Brand::find($id);
            if (!$brand) {
                return customResponse("Brand not found", 404);
            }
            $brand->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($brand, 200);
    } // end of updateBrands

    public function deleteBrand($id)
    {
        try {
            $brand = Brand::find($id);
            if (!$brand) {
                return customResponse("Brand not found", 404);
            }
            DB::beginTransaction();
            try {
                $brand->products()->delete();
                $brand->delete();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                return customResponse($e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse("Brand deleted successfully", 200);
    } // end of deleteBrands
}
