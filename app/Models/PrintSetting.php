<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;


class PrintSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function location(){
        
        return $this->belongsTo(Location::class, "location_id", "id");
    } // end of location
}
