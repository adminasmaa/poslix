<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use App\Models\Currency;

class CurrencyController extends Controller
{
    public function getCurrencies(Request $request)
    {
        $currency = null;
        if ($request->has('location_id') && $request->location_id) {
            $currency = Location::findOrFail($request->location_id)->currency_id;
        }
        try {
            if ($currency) {
                $currencies = Currency::findOrFail($currency);
            } else {
                $currencies = Currency::all();
                
            }
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($currencies);
    } // end of getCurrencies
}
