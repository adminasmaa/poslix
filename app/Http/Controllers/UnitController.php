<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Unit;

class UnitController extends Controller
{
    public function getUnits(){
        $units = Unit::all();
        return customResponse(['units' => $units], 200);
    }
}
