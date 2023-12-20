<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected  $guarded = [];
protected $fillable = [
    'id', 'location_id','name', 'enable_flag'
];
    protected $table = "payment_methods";

    public $timestamps = false;

    public function paymentMethod(){
        return $this->hasOne(Payment::class, "payment_type", "NAME");
    } // end of transaction

    public function location(){
        return $this->belongsTo(Location::class, "location_id", "id");
    } // end of location
}
