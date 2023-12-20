<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $table = 'business';

    protected $guarded=[];


    public function locations(){
        return $this->hasMany(Location::class, "business_id", "id");
    } // end of locations
}
