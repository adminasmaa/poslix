<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = "tailoring_package";

    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, "parent_id", "id");
    } // end of product

    public function type()
    {
        $this->belongsTo(PackageType::class, "tailoring_type_id", "id");
    } // end of type

    public function pricingGroups()
    {
        return $this->belongsToMany(PricingGroup::class, 'product_group_price', 'product_id', 'price_group_id')->withPivot('price', 'product_id');
    } // end of pricingGroups
}
