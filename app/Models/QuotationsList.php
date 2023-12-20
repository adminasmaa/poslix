<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationsList extends Model
{
    use HasFactory;

    protected  $guarded = [];

    protected $table = "quotation_list_headers";

    public $timestamps = true;

    // accessors for created_at
    public function getCreatedAtAttribute($value){
        return date("Y-m-d H:i:s", strtotime($value));
    } // end of getCreatedAtAttribute

    // accessors for updated_at
    public function getUpdatedAtAttribute($value){
        return date("Y-m-d H:i:s", strtotime($value));
    } // end of getUpdatedAtAttribute

    public function customer(){
        return $this->belongsTo(Customer::class, "customer_id", "id");
    } // end of transaction

    public function employee(){
        return $this->belongsTo(User::class, "employ_id", "id");
    } // end of transaction

    public function supplier(){
        return $this->belongsTo(Supplier::class, "supplier_id", "id");
    } // end of transaction

    public function quotation_list_lines(){
        return $this->hasMany(QuotationsListLines::class, "header_id", "id");
    } // end of transaction

    public function payment()
    {
        return $this->hasMany(Payment::class, "quotation_id", "id");
    } // end of payment

    public function products()
    {
        return $this->belongsToMany(Product::class, "quotation_list_lines", "header_id", "product_id");
    } // end of products


}
