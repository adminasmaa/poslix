<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\PackageType;
use Illuminate\Support\Facades\DB;



class productAllPricesController extends Controller
{
    public function getProductsAllPrices(Request $request, $location_id)
    {
        $products = Product::where('location_id', $location_id)
            ->with("packages","variations",'category','pricingGroups');
        if (isset($request->all_data) && $request->all_data)
            $products = $products->get();
        else
            $products = $products->paginate(10);
        foreach ($products as $product) {
            foreach ($product->packages as $package) {
                $package->prices_json = json_decode($package->prices_json);
            }
        }
        return customResponse($products, 200);
    }

    public function getProduct(Product $product)
    {
        $product->load(["packages", "variations",'category','pricingGroups']);
        if ($product->packages != null && count($product->packages) > 0) {
            foreach ($product->packages as $package) {
                $type = PackageType::find($package->tailoring_type_id);
                $type->load("sizes");
                $package->type = $type;
            }
        }

        $product_fabric_ids = DB::select("SELECT fabric_ids FROM tailoring_package WHERE parent_id = $product->id");

        if ($product_fabric_ids == null || count($product_fabric_ids) == 0) {
            $product->fabrics = [];
            $product->sell_over_stock = intval($product->sell_over_stock);
            return customResponse($product, 200);
        }

        $product_fabric_ids = explode(",", $product_fabric_ids[0]->fabric_ids);
        $product_fabric_ids = array_filter($product_fabric_ids);
        $product_fabric_ids = array_unique($product_fabric_ids);
        $product_fabric_ids = array_values($product_fabric_ids);
        $product_fabrics = Product::whereIn("id", $product_fabric_ids)->get();

        if ($product_fabrics != null && count($product_fabrics) > 0) {
            foreach ($product_fabrics as $fabric) {
                $fabric->sell_over_stock = intval($fabric->sell_over_stock);
            }
        }
        $product->fabrics = $product_fabrics;
        return customResponse($product, 200);
    }
}
