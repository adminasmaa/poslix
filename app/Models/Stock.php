<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected  $guarded = [];

    protected $table = "stock";

    public function transaction(){
        return $this->belongsTo(Transaction::class, "transaction_id", "id");
    } // end of transaction

    public function product(){
        return $this->belongsTo(Product::class, "product_id", "id");
    } // end of product

    public function variation(){
        return $this->belongsTo(Variation::class, "variation_id", "id");
    } // end of variation

    public function user(){
        return $this->belongsTo(User::class, "created_by", "id");
    } // end of user
}
