<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrintSettingRequest;
use App\Models\PrintSetting;
use Illuminate\Http\Request;

class PrintSettingController extends Controller
{
    public function store(PrintSettingRequest $request)
    {
        
        $validated = $request->validated();
        if($request->status == 0){
            $printSetting = PrintSetting::create($validated);
        }else{
            return('Printing Setting Status Has To 0');
        }
        return customResponse($printSetting);
    }

    public function show($locationId = null)
    {
        if ($locationId) {
            $printSetting = PrintSetting::where('location_id', $locationId)->first();
            if ($printSetting) {
                return customResponse($printSetting);
            }
        }
        return customResponse('Print Setting Not Found', 404);
    }

    public function showAll($locationId = null)
    {
        if ($locationId) {
            $printSetting = PrintSetting::where('location_id', $locationId)->get();
            if ($printSetting) {
                return customResponse($printSetting);
            }
        }
        return customResponse('Print Setting Not Found', 404);
    }


    public function update($id, PrintSettingRequest $request)
    {
        try{
            $validated = $request->validated();
            if($request->status == '1'){
                try{
                    $printSettingStatuss = PrintSetting::where('location_id',$request->location_id)
                                        ->where('status',1)
                                        ->get();
                    foreach($printSettingStatuss as $printSettingStatus){
                        $printSetting = $printSettingStatus->update([
                            "status" => 0
                        ]);
                    }
                }catch(\Exeption $e){
                    return customResponse($e->getMessage(), 500);
                }
            }
            $printSetting = PrintSetting::findOrFail($id);
            $printSetting->update($validated);
        }catch(\Exeption $e){
            return customResponse($e->getMessage(), 500);
        }
        
        return customResponse($printSetting);
    
    }

    
    function destroy(Request $request){
        $printSetting = PrintSetting::findOrFail($request->id)
                        ->where('location_id', $request->location_id)
                        ->where('id',$request->id);
        $printSetting->delete();
        return customResponse("Printing setting deleted successfully", 200);
    }
}
