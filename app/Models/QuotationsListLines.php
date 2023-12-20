<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationsListLines extends Model
{
    use HasFactory;

    protected  $guarded = [];

    protected $table = "quotation_list_lines";

    public $timestamps = false;

    public function quotation_list(){
        return $this->hasOne(QuotationsList::class, "id", "header_id");
       // return $this->hasOne(User::class, "id", "employ_id");
    } // end of transaction

    public function products()
    {
        return $this->hasMany(Product::class, "id", "product_id");
    }
    public function quotation_line_product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
