<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageType extends Model
{
    use HasFactory;

    protected $table = "tailoring_type";

    protected $guarded = [];

    public $timestamps = false;

    public function packages()
    {
        return $this->hasMany(Package::class, "tailoring_type_id", "id");
    } // end of packages

    public function sizes()
    {
        return $this->hasMany(TailoringPackageSize::class, "tailoring_type_id", "id");
    } // end of sizes

    public function location()
    {
        return $this->belongsTo(Location::class, "location_id", "id");
    } // end of location
}
