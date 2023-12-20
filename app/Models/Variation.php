<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variation extends Model
{
    use HasFactory;

    protected $table = "product_variations";

    protected $guarded = [];

    protected $appends = [
        "stock",
    ];

    public $timestamps = false;

    public function getStockAttribute()
    {
        return $this->stocks->sum("qty_received") - $this->stocks->sum("qty_sold");
    } // end of getStockAttribute

    public function product()
    {
        return $this->belongsTo(Product::class, "parent_id", "id");
    } // end of product

    public function stocks()
    {
        return $this->hasMany(Stock::class, "variation_id", "id");
    } // end of stocks

    public function pricingGroups()
    {
        return $this->belongsToMany(PricingGroup::class, 'product_group_price', 'variant_id', 'price_group_id')->withPivot('price', 'product_id');
    } // end of pricingGroups
}
