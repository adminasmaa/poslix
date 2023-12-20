<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Imports\ProductImport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Variation;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Location;
use Illuminate\Support\Facades\Validator;


class ProductController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:products/view"])->only(["getProducts", "getProduct"]);
        $this->middleware(["permissions:products/insert"])->only(["setProducts"]);
        $this->middleware(["permissions:products/edit"])->only(["updateProduct", "updateProductStatus", "transferProduct"]);
        $this->middleware(["permissions:products/delete"])->only(["deleteProduct"]);
    } // end of __construct

    public function getProducts(Request $request, $location_id)
    {
        $products = Product::where('location_id', $location_id)
            ->with("packages")->with("variations")->with('category');
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
    } // end of getProducts

    public function getProductsPricingGroup($location_id, $customer_id)
    {
        $products = Product::where('location_id', $location_id)
            ->with(["packages", "variations", "category"])->get();
        $customer = Customer::with([
            'pricingGroup.products',
            'pricingGroup.variants'
        ])->find($customer_id);

        if (!$customer) {
            return customResponse("Customer not found", 404);
        }

        if (!$customer->pricingGroup) {
            return customResponse("Customer has no pricing group", 404);
        }

        // update each product with the customer price that in the pivot between the product and the pricing group
        foreach ($customer->pricingGroup->products as $customer_product) {
            foreach ($products as $product) {
                if ($product->id == $customer_product->id) {
                    $product->sell_price = $customer_product->pivot->price;
                }
            }
        }

        // update each variation with the customer price that in the pivot between the variation and the pricing group
        foreach ($customer->pricingGroup->variants as $customer_variant) {
            foreach ($products as $product) {
                foreach ($product->variations as $variation) {
                    if ($variation->id == $customer_variant->id) {
                        $variation->price = $customer_variant->pivot->price;
                    }
                }
            }
        }

        return customResponse($products, 200);
    } // end of getProductsPricingGroup

    public function setProducts(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "category_id" => "required|numeric",
            "location_id" => "required|numeric",
            "type" => "required|string:in:single,package,variable,tailoring_package",
            "is_service" => "required|boolean",
            "is_fabric" => "required|boolean",
            "subproductname" => "nullable|string",
            "unit_id" => "required|exists:units,id",
            "brand_id" => "nullable|exists:brands,id",
            "never_tax" => "required|boolean",
            "alert_quantity" => "nullable|numeric",
            "sku" => "required|string",
            "barcode_type" => "required|string:in:C128,C39,C93,EAN8,EAN13,UPCA,UPCE",
            "image" => "nullable|url",
            "sell_price" => "required|numeric",
            "cost_price" => "required|numeric",
            "sell_over_stock" => "nullable|boolean",
            "qty_over_sold" => "nullable|numeric",
            "is_fifo" => "nullable|boolean",

            "packages" => "nullable|array",
            "packages.*.prices_json" => "nullable|array",
            "packages.*.prices_json.*.name" => "nullable|string",
            "packages.*.prices_json.*.from" => "nullable|numeric",
            "packages.*.prices_json.*.to" => "nullable|numeric",
            "packages.*.prices_json.*.price" => "nullable|numeric",
            "packages.*.tailoring_type_id" => "nullable|exists:tailoring_type,id",
            "packages.*.fabric_ids" => "nullable|array",
            "packages.*.fabric_ids.*" => "nullable|exists:products,id",

            "variations" => "nullable|array",
            "variations.*.name" => "nullable|string",
            "variations.*.sku" => "nullable|string",
            "variations.*.cost" => "nullable|numeric",
            "variations.*.price" => "nullable|numeric",
            "variations.*.sell_over_stock" => "nullable|boolean",
            "variations.*.is_selling_multi_price" => "nullable|numeric",
            "variations.*.is_service" => "nullable|boolean",
        ]);
        if ($validator) {
            return $validator;
        }
        $form_data = $request->except(["packages", "variations"]);
        $form_data["is_tailoring"] = 0;
        if ((!isset($form_data["image"])) || $form_data["image"] == null) {
            $form_data["image"] = "n";
        }
        $form_data["created_by"] = Auth::id();
        $form_data["is_disabled"] = 0;
        if ($form_data["sell_over_stock"] == null || (!isset($form_data["sell_over_stock"]))) {
            $form_data["sell_over_stock"] = 0;
        }
        $form_data["is_selling_multi_price"] = 0;
        if ((!isset($form_data["is_fifo"])) || $form_data["is_fifo"] == null) {
            $form_data["is_fifo"] = 0;
        }

        $product = Product::create($form_data);

        if ($form_data["type"] == "tailoring_package" || $form_data["type"] == "package") {
            if (!isset($request->packages)) {
                return customResponse("packages is required", 400);
            }
            foreach ($request->packages as $package) {
                $package['location_id'] = $form_data['location_id'];
                $package['parent_id'] = $product->id;
                $package['tailoring_type_id'] = $package['tailoring_type_id'];
                $package['prices_json'] = json_encode($package['prices_json']);
                $package['fabric_ids'] = implode(",", $package['fabric_ids']); // [1,2,3] => "1,2,3"
                if (substr($package['fabric_ids'], -1) != ",") {
                    $package['fabric_ids'] .= ",";
                }
                $package['created_by'] = Auth::id();
                Package::create($package);
            }
        } // end of package

        if ($form_data["type"] == "variable") {
            if (!isset($request->variations)) {
                return customResponse("variations is required", 400);
            }
            foreach ($request->variations as $variation) {
                $variation['location_id'] = $form_data['location_id'];
                $variation['parent_id'] = $product->id;
                $variation["name"] = $variation["name"];
                $variation["sku"] = $variation["sku"];
                $variation["cost"] = $variation["cost"];
                $variation["price"] = $variation["price"];
                $variation["sell_over_stock"] = $variation["sell_over_stock"] == 1 ? 1 : 0;
                $variation['is_selling_multi_price'] = $variation['is_selling_multi_price'] == 1 ? 1 : 0;
                $variation['is_service'] = $variation['is_service'] == 1 ? 1 : 0;
                $variation['is_active'] = 1;
                $variation['created_by'] = Auth::id();
                Variation::create($variation);
            }
        } // end of variable

        return customResponse([
            "product" => Product::where("id", $product->id)->with("variations")->with("packages")->first(),
            "message" => "Product Created Successfully"
        ], 200);
    } // end of setProducts

    public function getPackageTypes($location_id)
    {
        $package_type = PackageType::where("location_id", $location_id)->first();
        return customResponse(["package_type" => $package_type], 200);
    } // end of getPackageTypes

    public function getProduct(Product $product)
    {
        $product->load(["packages", "variations"]);
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

    public function updateProduct(Request $request, Product $product)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "category_id" => "required|numeric",
            "location_id" => "required|numeric",
            "type" => "required|string:in:single,package,variable,tailoring_package",
            "is_service" => "required|boolean",
            "is_fabric" => "required|boolean",
            "subproductname" => "nullable|string",
            "unit_id" => "required|exists:units,id",
            "brand_id" => "nullable|exists:brands,id",
            "never_tax" => "required|boolean",
            "alert_quantity" => "nullable|numeric",
            "sku" => "required|string",
            "barcode_type" => "required|string:in:C128,C39,C93,EAN8,EAN13,UPCA,UPCE",
            "image" => "nullable|url",
            "sell_price" => "required|numeric",
            "cost_price" => "required|numeric",
            "sell_over_stock" => "nullable|boolean",
            "qty_over_sold" => "nullable|numeric",
            "is_fifo" => "nullable|boolean",

            "packages" => "nullable|array",
            "packages.*.prices_json" => "nullable|array",
            "packages.*.prices_json.*.name" => "nullable|string",
            "packages.*.prices_json.*.from" => "nullable|numeric",
            "packages.*.prices_json.*.to" => "nullable|numeric",
            "packages.*.prices_json.*.price" => "nullable|numeric",
            "packages.*.tailoring_type_id" => "nullable|exists:tailoring_type,id",
            "packages.*.fabric_ids" => "nullable|array",
            "packages.*.fabric_ids.*" => "nullable|exists:products,id",

            "variations" => "nullable|array",
            "variations.*.name" => "nullable|string",
            "variations.*.sku" => "nullable|string",
            "variations.*.cost" => "nullable|numeric",
            "variations.*.price" => "nullable|numeric",
            "variations.*.sell_over_stock" => "nullable|boolean",
            "variations.*.is_selling_multi_price" => "nullable|numeric",
            "variations.*.is_service" => "nullable|boolean",
        ]);
        if ($validator) {
            return $validator;
        }
        $form_data = $request->except(["packages", "variations"]);
        $form_data["is_tailoring"] = 0;
        if ((!isset($form_data["image"])) || $form_data["image"] == null) {
            $form_data["image"] = "n";
        }
        $form_data["created_by"] = Auth::id();
        $form_data["is_disabled"] = 0;
        if ($form_data["sell_over_stock"] == null || (!isset($form_data["sell_over_stock"]))) {
            $form_data["sell_over_stock"] = 0;
        }
        $form_data["is_selling_multi_price"] = 0;
        if ((!isset($form_data["is_fifo"])) || $form_data["is_fifo"] == null) {
            $form_data["is_fifo"] = 0;
        }

        $product->update($form_data);

        if ($form_data["type"] == "tailoring_package" || $form_data["type"] == "package") {
            if (!isset($request->packages)) {
                return customResponse("packages is required", 400);
            }
            $product->packages()->delete();
            foreach ($request->packages as $package) {
                $package['location_id'] = $form_data['location_id'];
                $package['parent_id'] = $product->id;
                $package['tailoring_type_id'] = $package['tailoring_type_id'];
                $package['prices_json'] = json_encode($package['prices_json']);
                $package['fabric_ids'] = implode(",", $package['fabric_ids']); // [1,2,3] => "1,2,3"
                $package['created_by'] = Auth::id();
                Package::create($package);
            }
        } // end of package

        if ($form_data["type"] == "variable") {
            if (!isset($request->variations)) {
                return customResponse("variations is required", 400);
            }
            $product->variations()->delete();
            foreach ($request->variations as $variation) {
                $variation['location_id'] = $form_data['location_id'];
                $variation['parent_id'] = $product->id;
                $variation["name"] = $variation["name"];
                $variation["sku"] = $variation["sku"];
                $variation["cost"] = $variation["cost"];
                $variation["price"] = $variation["price"];
                $variation["sell_over_stock"] = $variation["sell_over_stock"] == 1 ? 1 : 0;
                $variation['is_selling_multi_price'] = $variation['is_selling_multi_price'] == 1 ? 1 : 0;
                $variation['is_service'] = $variation['is_service'] == 1 ? 1 : 0;
                $variation['is_active'] = 1;
                $variation['created_by'] = Auth::id();

                Variation::create($variation);
            }
        } // end of variable

        return customResponse([
            "product" => Product::where("id", $product->id)->with("variations")->with("packages")->first(),
            "message" => "Product updated Successfully"
        ], 200);
    } //end of updateProduct

    public function updateProductStatus(Request $request, Product $product)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "status" => "required|string:in:active,inactive",
        ]);
        if ($validator) {
            return $validator;
        }
        $product->update(["status" => $request->status]);
        return customResponse(["message" => "Product status updated Successfully"], 200);
    } //end of updateProductStatus

    public function deleteProduct(Request $request)
    {

        $validator = $this->validationApiTrait($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id'
        ]);
        if ($validator) {
            return $validator;
        }

        $productIds = $request->input('product_ids');

        DB::beginTransaction();
        try {
            // Delete variations and packages associated with each product
            Product::whereIn('id', $productIds)->each(function ($product) {
                $product->variations()->delete();
                $product->packages()->delete();
            });

            // Delete the products
            Product::whereIn('id', $productIds)->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Selected products deleted successfully'], 200);
    }

    public function deleteAll(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'confirmation' => 'required|boolean|accepted',
        ]);
        if ($validator) {
            return $validator;
        }

        $confirmation = $request->input('confirmation');

        if (!$confirmation) {
            return response()->json(['error' => 'Confirmation required'], 400);
        }

        DB::beginTransaction();
        try {
            $products = Product::all();

            foreach ($products as $product) {
                $product->variations()->delete();
                $product->packages()->delete();
            }

            Product::truncate();

            DB::commit();

            return response()->json(['success' => 'All products deleted successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function transferProduct(Request $request, $productId)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "location_id" => "required|exists:business_locations,id",
            "to_location_id" => "required|exists:business_locations,id",
            'cost_price' => 'nullable|numeric',
            'sell_price' => 'nullable|numeric',
        ]);
        if ($validator) {
            return $validator;
        }
        $product = Product::find($productId);
        if (!$product) {
            return customResponse(["message" => "Product not found or not exist in this location"], 400);
        } else {
            $productAttributes = $product->getAttributes();
            unset($productAttributes['id']);
            unset($productAttributes['created_at']);

            $productAttributes['location_id'] = $request->to_location_id;
            $productAttributes['created_by'] = Auth::id();
            $productAttributes['sell_over_stock'] = 0;
            $productAttributes['qty_over_sold'] = 0;
            $productAttributes['alert_quantity'] = 0;

            if ($request->has('cost_price')) {
                $productAttributes['cost_price'] = $request->cost_price;
            }
            if ($request->has('sell_price')) {
                $productAttributes['sell_price'] = $request->sell_price;
            }

            $newProduct = Product::create($productAttributes);
        }

        $productVariations = Variation::where("parent_id", $product->id)->get();
        if ($productVariations) {
            foreach ($productVariations as $variation) {
                $variationAttributes = $variation->getAttributes();
                unset($variationAttributes['id']);
                unset($variationAttributes['created_at']);

                $variationAttributes['location_id'] = $request->to_location_id;
                $variationAttributes['created_by'] = Auth::id();
                $variationAttributes['parent_id'] = $newProduct->id;
                $variationAttributes['sell_over_stock'] = 0;

                if ($request->has('cost_price')) {
                    $variationAttributes['cost'] = $request->cost_price;
                }
                if ($request->has('sell_price')) {
                    $variationAttributes['price'] = $request->sell_price;
                }

                Variation::create($variationAttributes);
            }
        }

        $productPackages = Package::where("parent_id", $product->id)->get();
        if ($productPackages) {
            foreach ($productPackages as $package) {
                $packageAttributes = $package->getAttributes();
                unset($packageAttributes['id']);
                unset($packageAttributes['created_at']);

                $packageAttributes['location_id'] = $request->to_location_id;
                $packageAttributes['parent_id'] = $newProduct->id;
                $packageAttributes['created_by'] = Auth::id();

                Package::create($packageAttributes);
            }
        }

        return $this->getProduct($newProduct);
    } //end of transferProduct

    public function import(Request $request, $locationId)
    {
        $checkLocation = Location::find($locationId);
        if (!$checkLocation) return customResponse("Location not found", 404);
        $validator = $this->validationApiTrait($request->all(), [
            "file" => "required|file|mimes:xlsx,xls,csv",
        ]);
        if ($validator) {
            return $validator;
        }
        $file = $request->file('file');

        $array = Excel::toArray(new ProductImport, $file);
        $header = $array[0][0];
        if (strtolower($header[1]) != 'type') {
            return customResponse('second column must be `type`', 400);
        }
        if (strtolower($header[2]) != 'sku') {
            return customResponse('third column must be `sku`', 400);
        }
        if (strtolower($header[3]) != 'name') {
            return customResponse('fourth column must be `name`', 400);
        }
        if (strtolower($header[4]) != 'sell') {
            return customResponse('fifth column must be `sell`', 400);
        }
        if (strtolower($header[5]) != 'cost') {
            return customResponse('sixth column must be `cost`', 400);
        }
        if (strtolower($header[6]) != 'category') {
            return customResponse('seventh column must be `category`', 400);
        }
        if (strtolower($header[7]) != 'brand') {
            return customResponse('seventh column must be `brand`', 400);
        }
        if (strtolower($header[8]) != 'unit') {
            return customResponse('seventh column must be `unit`', 400);
        }
        if (strtolower($header[9]) != 'qty') {
            return customResponse('eighth column must be `qty`', 400);
        }
        if (strtolower($header[10]) != 'sell over stock') {
            return customResponse('eighth column must be `sell over stock`', 400);
        }
        if (strtolower($header[11]) != 'image') {
            return customResponse('eighth column must be `image`', 400);
        }
        unset($array[0][0]); // remove header
        $array[0] = array_values($array[0]); // reindex array
        $data = $array[0];
        $filteredData = array_filter($data, function ($subarray) {
            return !empty(array_filter($subarray, function ($item) {
                return $item !== null;
            }));
        });
        $messageArray = [];
        try {
            foreach ($filteredData as $product) {
                $checkProductName = Product::where('name', $product[3])
                    ->where('location_id', $locationId)
                    ->first();
                if ($checkProductName) {
                    $messageArray[] = $product[3];
                    continue;
                }
                // category
                $category = Category::where('name', $product[6])
                    ->where('location_id', $locationId)
                    ->first();
                if (!$category) {
                    $categoryId = Category::create([
                        'name' => $product[6],
                        'parent_id' => 0,
                        'location_id' => $locationId,
                        'created_by' => Auth::id(),
                    ])->id;
                } else {
                    $categoryId = $category->id;
                }
                // brand
                $brand = Brand::where('name', $product[7])
                    ->where('location_id', $locationId)
                    ->first();
                if (!$brand) {
                    $brandId = Brand::create([
                        'name' => $product[7],
                        'description' => $product[7],
                        'location_id' => $locationId,
                        'created_by' => Auth::id(),
                    ])->id;
                } else {
                    $brandId = $brand->id;
                }
                // unit
                $unit = Unit::where('name', $product[8])
                    ->first();
                if (!$unit) {
                    $unitId = Unit::insert([
                        'name' => $product[8],
                        'created_at' => now(),
                    ])->id;
                } else {
                    $unitId = $unit->id;
                }
                $data = [
                    'name' => $product[3],
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'unit_id' => $unitId,
                    'location_id' => $locationId,
                    'type' => $product[1],
                    'is_service' => 0,
                    'is_fabric' => 0,
                    'never_tax' => 0,
                    'sell_over_stock' => $product[10],
                    'sku' => $product[2],
                    'barcode_type' => 'C128',
                    'sell_price' => $product[4],
                    'cost_price' => $product[5],
                    'is_disabled' => 0,
                    'is_selling_multi_price' => 0,
                    'is_tailoring' => 0,
                    'image' => $product[11],
                    'subproductname' => null,
                    'created_by' => Auth::id(),
                ];
                $productCreated = Product::create($data);
                $productCreated->stocks()->create([
                    'qty_received' => $product[9],
                    'qty_sold' => 0,
                    'variation_id' => 0,
                    'created_by' => Auth::id(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error in import products: " . $e->getMessage() . " in file => " . $e->getFile() . " in line => " . $e->getLine());
            return customResponse("Please make sure all fields is set in excel and nothing is empty", 400);
        }
        if ($messageArray) {
            return customResponse([
                'message' => 'The following products already exist, But others added',
                'products' => $messageArray,
            ], 200);
        }
        return customResponse('imported successfully', 200);
    } //end of import

    public function searchProducts(Request $request, $location_id)
    {
        $validator = $this->validationApiTrait($request->all(), [
            "search" => "required|string",
        ]);
        if ($validator) return $validator;
        if (!Location::find($request->location_id)) return customResponse("Location not found", 400);
        $products = Product::where('location_id', $request->location_id)
            ->where('name', 'like', '%' . $request->search . '%')
            ->with("stocks")->with("packages")->with("variations.stocks")->with('category')->get();
        foreach ($products as $product) {
            foreach ($product->packages as $package) {
                $package->prices_json = json_decode($package->prices_json);
            }
        }

        return customResponse($products, 200);
    } // end of searchProducts

    public function getFabrics(Request $request, $location_id)
    {
        $products = Product::where('location_id', $location_id)
            ->where('is_fabric', 1)
            ->with("stocks")->with("packages")->with("variations.stocks")->with('category')->get();
        foreach ($products as $product) {
            foreach ($product->packages as $package) {
                $package->prices_json = json_decode($package->prices_json);
            }
        }

        return customResponse($products, 200);
    } // end of getFabrics
}
