<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Jobs\DeleteInvalidVariantPriceGroup;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Http\Request;
use App\Models\PricingGroup;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class PricingGroupController extends Controller
{
    use GeneralTrait;

    public function getPricingGroup(Request $request, $location_id)
    {
        $groups = PricingGroup::where('location_id', $location_id)
            ->with([
                'customers:id,type,first_name,price_groups_id',
            ]);
        if ($request->has('group_id')) {
            $groups = $groups->where('id', $request->group_id)
                ->get();
        } else {
            if ($request->has('all_data') && $request->all_data) {
                $groups = $groups->get();
            } else {
                $groups = $groups->paginate(10);
            }
        }
        foreach ($groups as $group) {
            $productPriceWithoutVariants = DB::table('product_group_price')
                ->join('products', 'products.id', '=', 'product_group_price.product_id')
                ->select('products.id as id', 'products.name as name', 'product_group_price.price as price')
                ->where('product_group_price.price_group_id', $group->id)
                ->where('products.type', 'single')
                ->get();
            foreach ($productPriceWithoutVariants as $key => $product) {
//                $productPriceWithoutVariants[$key]->is_variant = false;
                $productPriceWithoutVariants[$key]->variants = [];
            }

            $productPriceWithVariants = DB::table('product_group_price')
                ->join('products', 'products.id', '=', 'product_group_price.product_id')
                ->select('products.id as id', 'products.name as name', 'product_group_price.price as price')
                ->where('product_group_price.price_group_id', $group->id)
                ->where('products.type', 'variable')
                ->get();
            // remove duplicates
            $productPriceWithVariants = $productPriceWithVariants->unique('id');

            // add variants to products
            foreach ($productPriceWithVariants as $key => $product) {
                $variants = DB::table('product_group_price')
                    ->join('product_variations', 'product_variations.id', '=', 'product_group_price.variant_id')
                    ->select('product_variations.id as id', 'product_variations.name as name', 'product_group_price.price as price')
                    ->where('product_group_price.price_group_id', $group->id)
                    ->where('product_group_price.product_id', $product->id)
                    ->get();
//                $productPriceWithVariants[$key]->is_variant = true;
                $product->variants = $variants;
            }
            $productPrices = $productPriceWithoutVariants->merge($productPriceWithVariants);
            /**
             * assign old price to variants
            */
            foreach ($productPrices as $key => $product) {
                $oldPrice = Product::select('id', 'sell_price')
                    ->where('id', $product->id)
                    ->first();
                if ($oldPrice) {
                    $product->old_price = round($oldPrice->sell_price,2);
                }
                $variantsArray = [];
                foreach ($product->variants as $keyVariant => $productVariant) {
                    $variants = Variation::where('parent_id', $product->id)
                        ->select('id', 'name', 'price', 'parent_id')
                        ->where('id', $productVariant->id)
                        ->first();
                    if ($variants) {
                        $variantsArray[] = $variants->id;
                        $productVariant->id = $productVariant->id ?? $variants->id;
                        $productVariant->name = $productVariant->name ?? $variants->name;
                        $productVariant->price = round($productVariant->price,2) ?? null;
                        $productVariant->old_price = round($variants->price,2);
//                        $productPrices[$key]->is_variant = true;
                    }
                }
                $variants = Variation::where('parent_id', $product->id)
                    ->select('id', 'name', 'price', 'parent_id')
                    ->whereNotIn('id', $variantsArray)
                    ->get();
                foreach ($variants as $key => $variant) {
                    $variantsArray[] = $variant->id;
                    $product->variants[] = [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => null,
                        'old_price' => round($variant->price,2),
                    ];
                }
            }
            /**
             * assign old price to products
            */
            $group->products = $productPrices;
        }

        return customResponse($groups, 200);
    } // end of get pricing group

    public function addPricingGroup(Request $request)
    {
        //$paymentId = PaymentMethod::find($request->name)->id;
        $validation = $this->validationApiTrait($request->all(), [
            "name" => "required|string",
            "location_id" => "required|numeric|exists:business_locations,id",
            "business_id" => "numeric|exists:business,id",
            "customers" => "array",
            "customers.*" => "numeric|exists:contacts,id",
            "products" => "array",
            "products.*.id" => "numeric|exists:products,id",
            "products.*.price" => "required_if:products.*.variants,null|numeric",
            "products.*.variants" => "nullable|array",
            "products.*.variants.*.id" => "numeric|exists:product_variations,id",
            "products.*.variants.*.price" => "numeric"
        ]);

        if ($validation) {
            return $validation;
        }

        DB::beginTransaction();
        try {
            $group = PricingGroup::create([
                "name" => $request->name,
                "is_active" => 1, // default value
                "location_id" => $request->location_id,
                "business_id" => 0,
            ]);

            if($request->has('customers'))
            {
                foreach ($request->customers as $customer) {
                    if ($customer == 1) {
                        continue;
                    }
                    $checkCustomer = Customer::where('id', $customer)
                        ->where('location_id' , $request->location_id )
                        ->first();
                    if (!$checkCustomer) {
                        DB::rollback();
                        return customResponse("Customer {$customer} not match with location id", 404);
                    }
                    Customer::where('id', $customer)
                        ->where('location_id' , $request->location_id )
                        ->update([
                            "price_groups_id" => $group->id
                    ]);
                }
            }

            if($request->has('products'))
            {
                foreach ($request->products as $product) {
                    if (isset($product['variants']) && !empty($product['variants'])) {
                        foreach ($product['variants'] as $variant) {
                            $group->variants()->syncWithoutDetaching([$variant['id'] => ['price' => $variant['price'], 'product_id' => $product['id']]]);
                        }
                    } else {
                        $group->products()->syncWithoutDetaching([$product['id'] => ['price' => $product['price'] ?? null]]);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("error in add pricing group " . $e->getMessage() . " in line " . $e->getLine());
            return customResponse($e->getMessage() . " in line => " . $e->getLine(), 500);
        }

        return customResponse($group, 200);
    }

    public function updatePricingGroup(Request $request, $id)
    {
        $validation = $this->validationApiTrait($request->all(), [
            "name" => "string",
            "location_id" => "required|numeric|exists:business_locations,id",
            "is_active" => "nullable|boolean",
            "customers" => "array",
            "customers.*.id" => "numeric|exists:contacts,id",
            "products" => "array",
            "products.*.id" => "numeric|exists:products,id",
            "products.*.price" => "required_if:products.*.variants,null|numeric",
            "products.*.variants" => "nullable|array",
            "products.*.variants.*.id" => "numeric|exists:product_variations,id",
            "products.*.variants.*.price" => "numeric"
        ]);

        if ($validation) {
            return $validation;
        }

        $group = PricingGroup::find($id);
        if (!$group) {
            return customResponse('Pricing group not found', 404);
        }
        DB::beginTransaction();
        try {
            $group->update([
                "name" => $request->name ?? $group->name,
                "is_active" => $request->is_active ?? $group->is_active,
            ]);
            if($request->has('customers'))
            {
                foreach ($request->customers as $customer) {
                    if ($customer == 1) {
                        continue;
                    }
                    $checkCustomer = Customer::where('id', $customer)
                        ->where('location_id' , $request->location_id )
                        ->first();
                    if (!$checkCustomer) {
                        DB::rollback();
                        return customResponse("Customer {$customer} not match with location id", 404);
                    }
                    Customer::where('id', $customer)
                        ->where('location_id' , $request->location_id )
                        ->update([
                            "price_groups_id" => $group->id
                        ]);
                }
            }

            if($request->has('products'))
            {
                foreach ($request->products as $product) {
                    if (isset($product['variants']) && !empty($product['variants'])) {
                        foreach ($product['variants'] as $variant) {
                            $group->variants()->syncWithoutDetaching([$variant['id'] => ['price' => $variant['price'], 'product_id' => $product['id']]]);
                        }
                    } else {
                        $group->products()->syncWithoutDetaching([$product['id'] => ['price' => $product['price'] ?? null]]);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return customResponse($e->getMessage(), 500);
        }
        // fire the job delete invalid pricing group
        dispatch(new DeleteInvalidVariantPriceGroup())->afterResponse();
        return customResponse('updated successfully', 200);
    } // end of update pricing group

    public function deletePricingGroup($id)
    {
        $group = PricingGroup::find($id);
        if (!$group) {
            return customResponse('Pricing group not found', 404);
        }
        DB::beginTransaction();
        try {
            $group->products()->detach();
            $group->variants()->detach();
            $group->delete();
            DB::table('contacts')
                ->where('price_groups_id', $id)
                ->update(['price_groups_id' => null]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return customResponse($e->getMessage(), 500);
        }
        return customResponse('Deleted Successfully', 200);
    } // delete pricing group
}
