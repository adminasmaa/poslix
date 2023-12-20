<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationProducts extends Model
{
    use HasFactory;
    protected  $guarded = [];

    protected $table = "quotation_products";
    public $timestamps = false;

}
