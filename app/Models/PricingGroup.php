<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingGroup extends Model
{
    use HasFactory;

    protected  $guarded = [];

    protected $table = "selling_price_groups";

    public function customers(){
        return $this->hasMany(Customer::class, 'price_groups_id', 'id');
    } // end of customers

    public function location(){
        return $this->belongsTo(Location::class, 'location_id', 'id');
    } // end of location

    public function products(){
        return $this->belongsToMany(Product::class, 'product_group_price', 'price_group_id', 'product_id')->withPivot('price'/*, 'variant_id'*/);
    } // end of products

    public function variants(){
        return $this->belongsToMany(Variation::class, 'product_group_price', 'price_group_id', 'variant_id')->withPivot('price', 'product_id');
    } // end of variants
}
