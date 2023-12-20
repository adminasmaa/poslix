<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Location;
use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    use generalTrait;

    public function getAppearance($location_id, Request $request)
    {
        $lang = $request->header('lang');
        $businessLocation = Location::where('id', $location_id)->first();
        if (!$businessLocation) {
            return customResponse("Location not found", 404);
        }
        $appearance = $businessLocation->invoice_details;
        // get json data from appearance column
        $appearance = json_decode($appearance);

        // add is_multi_language to $appearance
        $appearance->is_multi_language = $businessLocation->is_multi_language;

        $finalAppearance = $this->getData($appearance, $lang);
        return customResponse($finalAppearance, 200);
    }

    public function setAppearance(Request $request)
    {
        $langs = ['ar', 'en'];
        foreach ($langs as $lang) {
            $validator = $this->validationApiTrait($request->all(), [
                "location_id" => "required|exists:business_locations,id",
                $lang . ".name" => "required|string",
                $lang . ".tell" => "required|numeric",
                $lang . ".txtCustomer" => "required|string",
                $lang . ".orderNo" => "required|string",
                $lang . ".txtDate" => "required|string",
                $lang . ".txtQty" => "required|string",
                $lang . ".txtItem" => "required|string",
                $lang . ".txtAmount" => "required|string",
                $lang . ".txtTax" => "required|string",
                $lang . ".txtTotal" => "required|string",
                $lang . ".footer" => "required|string",
                $lang . ".email" => "required|string",
                $lang . ".address" => "required|string",
                $lang . ".vatNumber" => "required|string",
                $lang . ".customerNumber" => "required|string",
                $lang . ".description" => "required|string",
                $lang . ".unitPrice" => "required|string",
                $lang . '.subTotal' => 'required|string',
                'logo' => 'nullable|string',
                'website' => 'nullable|string',
                'instagram' => 'nullable|string',
                'whatsapp' => 'nullable|string',
            ]);
            if ($validator) {
                return $validator;
            }
        }

        $businessLocation = Location::where('id', $request->location_id)->first();
        if (!$businessLocation) {
            return customResponse("Location not found", 404);
        }
        $appearance = $businessLocation->invoice_details;
        // get json data from appearance column
        $appearance = json_decode($appearance);

        foreach ($langs as $lang) {
            // update json data
            if (isset($request->logo) && $request->logo != '') {
                $appearance->logo = $request->logo;
            }
            $appearance->website = $request->website ?? $appearance->website ?? 'poslix.com';
            $appearance->instagram = $request->instagram ?? $appearance->instagram ?? 'instagram';
            $appearance->whatsapp = $request->whatsapp ?? $appearance->whatsapp ?? 'whatsapp';
            $appearance->{$lang} = (object)[
                'name' => $request->{$lang}['name'] ?? $appearance->{$lang}['name'] ?? '',
                'tell' => $request->{$lang}['tell'] ?? $appearance->{$lang}['tell'] ?? '',
                'txtCustomer' => $request->{$lang}['txtCustomer'] ?? $appearance->{$lang}['txtCustomer'] ?? '',
                'orderNo' => $request->{$lang}['orderNo'] ?? $appearance->{$lang}['orderNo'] ?? '',
                'txtDate' => $request->{$lang}['txtDate'] ?? $appearance->{$lang}['txtDate'] ?? '',
                'txtQty' => $request->{$lang}['txtQty'] ?? $appearance->{$lang}['txtQty'] ?? '',
                'txtItem' => $request->{$lang}['txtItem'] ?? $appearance->{$lang}['txtItem'] ?? '',
                'txtAmount' => $request->{$lang}['txtAmount'] ?? $appearance->{$lang}['txtAmount'] ?? '',
                'txtTax' => $request->{$lang}['txtTax'] ?? $appearance->{$lang}['txtTax'] ?? '',
                'txtTotal' => $request->{$lang}['txtTotal'] ?? $appearance->{$lang}['txtTotal'] ?? '',
                'footer' => $request->{$lang}['footer'] ?? $appearance->{$lang}['footer'] ?? '',
                'email' => $request->{$lang}['email'] ?? $appearance->{$lang}['email'] ?? '',
                'address' => $request->{$lang}['address'] ?? $appearance->{$lang}['address'] ?? '',
                'vatNumber' => $request->{$lang}['vatNumber'] ?? $appearance->{$lang}['vatNumber'] ?? '',
                'customerNumber' => $request->{$lang}['customerNumber'] ?? $appearance->{$lang}['customerNumber'] ?? '',
                'description' => $request->{$lang}['description'] ?? $appearance->{$lang}['description'] ?? '',
                'unitPrice' => $request->{$lang}['unitPrice'] ?? $appearance->{$lang}['unitPrice'] ?? '',
                'subTotal' => $request->{$lang}['subTotal'] ?? $appearance->{$lang}['subTotal'] ?? '',
            ];
        }

        $businessLocation->update([
            "invoice_details" => json_encode($appearance),
            "is_multi_language" => $request->is_multi_language ?? $businessLocation->is_multi_language ?? "0",
        ]);
        // add is_multi_language to $appearance
        $appearance->is_multi_language = $businessLocation->is_multi_language ?? "0";
        // get some data from appearance column
        $finalAppearance = $this->getData($appearance);
        return customResponse($finalAppearance, 200);
    }

    private function getData($appearance, $lang = null)
    {
        $dataAr = [
            "is_multi_language" => $appearance->is_multi_language ?? "0",
            "logo" => $appearance->logo ?? '',
            "name" => $appearance->ar->name ?? '',
            "tell" => $appearance->ar->tell ?? '',
            "txtCustomer" => $appearance->ar->txtCustomer ?? '',
            "orderNo" => $appearance->ar->orderNo ?? '',
            "txtDate" => $appearance->ar->txtDate ?? '',
            "txtQty" => $appearance->ar->txtQty ?? '',
            "txtItem" => $appearance->ar->txtItem ?? '',
            "txtAmount" => $appearance->ar->txtAmount ?? '',
            "txtTax" => $appearance->ar->txtTax ?? '',
            "txtTotal" => $appearance->ar->txtTotal ?? '',
            "footer" => $appearance->ar->footer ?? '',
            "email" => $appearance->ar->email ?? '',
            "address" => $appearance->ar->address ?? '',
            "vatNumber" => $appearance->ar->vatNumber ?? '',
            "customerNumber" => $appearance->ar->customerNumber ?? '',
            "description" => $appearance->ar->description ?? '',
            "unitPrice" => $appearance->ar->unitPrice ?? '',
            "subTotal" => $appearance->ar->subTotal ?? '',
            "website" => $appearance->website ?? '',
            "instagram" => $appearance->instagram ?? '',
            "whatsapp" => $appearance->whatsapp ?? '',
        ];

        $dataEn = [
            "is_multi_language" => $appearance->is_multi_language ?? "0",
            "logo" => $appearance->logo ?? '',
            "name" => $appearance->en->name ?? '',
            "tell" => $appearance->en->tell ?? '',
            "txtCustomer" => $appearance->en->txtCustomer ?? '',
            "orderNo" => $appearance->en->orderNo ?? '',
            "txtDate" => $appearance->en->txtDate ?? '',
            "txtQty" => $appearance->en->txtQty ?? '',
            "txtItem" => $appearance->en->txtItem ?? '',
            "txtAmount" => $appearance->en->txtAmount ?? '',
            "txtTax" => $appearance->en->txtTax ?? '',
            "txtTotal" => $appearance->en->txtTotal ?? '',
            "footer" => $appearance->en->footer ?? '',
            "email" => $appearance->en->email ?? '',
            "address" => $appearance->en->address ?? '',
            "vatNumber" => $appearance->en->vatNumber ?? '',
            "customerNumber" => $appearance->en->customerNumber ?? '',
            "description" => $appearance->en->description ?? '',
            "unitPrice" => $appearance->en->unitPrice ?? '',
            "subTotal" => $appearance->en->subTotal ?? '',
            "website" => $appearance->website ?? '',
            "instagram" => $appearance->instagram ?? '',
            "whatsapp" => $appearance->whatsapp ?? '',
        ];

        if ($lang == 'ar') {
            return $dataAr;
        } elseif ($lang == 'en') {
            return $dataEn;
        }
        return [
            "ar" => $dataAr,
            "en" => $dataEn
        ];
    }
}
