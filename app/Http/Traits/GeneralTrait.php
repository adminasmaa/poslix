<?php

namespace App\Http\Traits;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Support\Facades\Validator;

trait GeneralTrait
{
    public function notFound()
    {
        return response()->json([
            'status' => false,
            'message' => 'Not Found'
        ], 404);
    }

    public function apiSuccessResponse($data = null, $seo = null, $message = 'Success')
    {
        $response = [
            'status' => true,
            'message' => $message,
            'url' => url('/') . '/',
            'data' => $data,
            'seo' => $seo,
        ];
        return response()->json($response, 200);
    }

    public function apiCreatedResponse($data = null, $message = 'Created')
    {
        $response = [
            'status' => true,
            'message' => $message,
            'data' => $data,
        ];
        return response()->json($response, 201);
    }

    public function validationTrait($request, $rules)
    {
        $validator = Validator::make($request, $rules);
        if ($validator->fails()) {
            return $validator;
        } else {
            return false;
        }
    }

    public function validationApiTrait($request, $rules, $messages = null): bool|\Illuminate\Http\JsonResponse
    {
        if ($messages) {
            $validator = Validator::make($request, $rules, $messages);
        } else {
            $validator = Validator::make($request, $rules);
        }
        if ($validator && $validator->fails()) {
            return customResponse($validator->errors(), 422);
        } else {
            return false;
        }
    }

    public function validationWebTrait($request, $rules)
    {
        $validator = Validator::make($request, $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            return false;
        }
    }

    public function arrayAltForImages()
    {
        return ['web design', 'web development', 'mobile app', 'seo', 'digital marketing', 'social media marketing', 'social media management', 'social media ads', 'social media content', 'social media strategy', 'social media campaign', 'social media analytics', 'team', 'team members'];
    }

    public function updateStock($product_id, $variation_id = null, $qty)
    {
        if ($variation_id) {
            $stocks = Variation::find($variation_id)->stocks;
            foreach ($stocks as $stock) {
                if ($stock->qty_received - $stock->qty_sold >= $qty) {
                    $stock->qty_sold += $qty;
                    $stock->save();
                    return $stock->id;
                } else {
                    $qty -= $stock->qty_received - $stock->qty_sold;
                    $stock->qty_sold = $stock->qty_received;
                    $stock->save();
                }
            }
            return false;
        }

        $stocks = Product::find($product_id)->stocks;
        foreach ($stocks as $stock) {
            if ($stock->qty_received - $stock->qty_sold >= $qty) {
                $stock->qty_sold += $qty;
                $stock->save();
                return $stock->id;
            } else {
                $qty -= $stock->qty_received - $stock->qty_sold;
                $stock->qty_sold = $stock->qty_received;
                $stock->save();
            }
        }
        return false;
    }
}
