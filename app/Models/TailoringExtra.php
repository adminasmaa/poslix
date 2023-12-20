<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TailoringExtra extends Model
{
    use HasFactory;

    protected $table = 'tailoring_extra';

    protected $guarded = [];

    public $timestamps = false;

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    } // end of location
}
