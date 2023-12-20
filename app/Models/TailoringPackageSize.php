<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TailoringPackageSize extends Model
{
    use HasFactory;

    protected $table = 'tailoring_sizes';

    protected $guarded = [];

    public $timestamps = false;

    public function packageType()
    {
        return $this->belongsTo(PackageType::class, 'tailoring_type_id', 'id');
    } // end of package
}
