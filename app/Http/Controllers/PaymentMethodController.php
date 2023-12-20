<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    use generalTrait;

    public function getPayments($location_id)
    {
        $payments = PaymentMethod::where('location_id', '=', $location_id)->get();
        return customResponse(['payments' => $payments], 200);
    }

    public function addPayment(Request $request)
    {
        //$paymentId = PaymentMethod::find($request->name)->id;
        $validator = $this->validationApiTrait($request->all(), [
            "location_id" => "required|numeric|exists:business_locations,id",
            // in cash, card, cheque, bank
            "name" => "required|string|in:cash,card,cheque,bank",
        ]);
        if ($validator) {
            return $validator;
        }

        $paymentt = PaymentMethod::create([
            "location_id" => $request->location_id,
            "name" => $request->name,
            "enable_flag" => 1,
        ]);


        return customResponse(['payment' => $paymentt], 200);
    }

    function updatePayment(Request $request,$id){
        $validator = $this->validationApiTrait($request->all(), [
            "location_id" => "numeric|exists:business_locations,id",
            "name" => "string",
            "enable_flag" => "boolean"
        ]);
        if ($validator) {
            return $validator;
        }
        $paymentt = PaymentMethod::find($id);
        if(!$paymentt){
            return customResponse('Payment method not found', 404);
        }
        try{
                $paymentData = [
                    "location_id" => $request->has('location_id') ? $request->location_id : $paymentt->location_id,
                    "name" => $request->has('name') ? $request->name : $paymentt->name,
                    "enable_flag" => $request->has('enable_flag') ? $request->enable_flag : $paymentt->enable_flag,
                ];
                $paymentt->update($paymentData);
        }catch(\Exeption $e){
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($paymentt, 200);
    }

    function deletePayment($id){
        $paymentt = PaymentMethod::find($id);
        if(!$paymentt){
            return customResponse('Payment method not found', 404);
        }
        $paymentt->delete();
        return customResponse('Payment mathod deleted successfully', 200);
    }
}
