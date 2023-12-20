<?php

namespace App\Http\Controllers;
use App\Http\Traits\GeneralTrait;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Variation;


class DigitalMenuController extends Controller
{
    use GeneralTrait;

    public function sendProducts(Request $request)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric",
            "transferred_location_id" => "required|numeric",
            "cart.*.product_id" => "required|numeric:exists:products,id",
            "cart.*.variation_id" => "nullable|numeric:exists:variations,id",
        ]);
        if ($validation) {
            return $validation;
        }
        $newProduct = null;     
        foreach ($request->cart as $item) {
            if (isset($item['variation_id']) && $item['variation_id'] != null) {
                $product = Product::find($item['product_id'])
                    ->where('location_id', $request->location_id)
                    ->with(['variations' => function ($query) use ($item) {
                        if (isset($item['variation_id']) && $item['variation_id'] != null) {
                            $query->where('id', $item['variation_id']);
                        }}])
                    ->first();
                $transferred_product = Product::where('id', $item['product_id'])
                    ->where('location_id', $request->transferred_location_id)
                    ->with(['variations' => function ($query) use ($item) {
                        if (isset($item['variation_id']) && $item['variation_id'] != null) {
                            $query->where('id', $item['variation_id']);
                        }}])
                    ->first();
            } else {
                $product = Product::where('id', $item['product_id'])
                    ->where('location_id', $request->location_id)
                    ->first();
                $transferred_product = Product::where('id', $item['product_id'])
                    ->where('location_id', $request->transferred_location_id)
                    ->first();
            }
            if ($product) {
                $newProduct = $product->replicate();
                $newProduct->location_id = $request->transferred_location_id;

                if ($newProduct->category && $newProduct->category != null) {
                    $existingCategory = Category::where('location_id', $request->transferred_location_id)
                        ->where('name', $newProduct->category->name)
                        ->first();
                    if ($existingCategory) {
                        $newProduct->category_id = $existingCategory->id;
                    } else {
                        $newProductCategory = Category::find($newProduct->category_id)->replicate();
                        $newProductCategory->location_id = $request->transferred_location_id;
                        $newProductCategory->save();
                        $newProduct->category_id = $newProductCategory->id;
                    }
                }

                if($newProduct->brand && $newProduct->brand != null){
                    $existingBrand = Brand::where('location_id', $request->transferred_location_id)
                    ->where('name', $newProduct->brand->name)
                    ->first();
                    if ($existingBrand) {
                        $newProduct->brand_id = $existingBrand->id;
                    } else {
                        $newProductBrand = Brand::find($newProduct->brand_id)->replicate();
                        $newProductBrand->location_id = $request->transferred_location_id;
                        $newProductBrand->save();
                        $newProduct->brand_id = $newProductBrand->id;
                    }
                }
                if($newProduct->variation_id != 0 && $newProduct->variation_id != null){
                    $existingvariation = Variation::where('location_id', $request->transferred_location_id)
                    ->where('id', $newProduct->variation_id)
                    ->first();
                    if ($existingvariation) {
                        $newProduct->variation_id = $existingvariation->id;
                    } else {
                        $newProductVariation = Variation::find($newProduct->variation_id)->replicate();
                        $newProductVariation->location_id = $request->transferred_location_id;
                        $newProductVariation->save();
                        $newProduct->variation_id = $newProductVariation->id;
                    }
                }

                $newProduct->save();
            }
        }
        return customResponse($newProduct, 200);
    }

}
