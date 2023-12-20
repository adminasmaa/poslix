<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected  $guarded = [];

    protected $appends = [
        "stock",
        "total_qty_sold",
    ];

    // accessors for sell_over_stock
    public function getSellOverStockAttribute($value)
    {
        return intval($value);
    } // end of getSellOverStockAttribute

    public function getStockAttribute()
    {
        return $this->stocks->sum("qty_received") - $this->stocks->sum("qty_sold");
    } // end of getStockAttribute

    public function getTotalQtySoldAttribute()
    {
        return $this->stocks->sum("qty_sold");
    } // end of getQytSoldAttribute

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('module', function (Builder $builder) {
            $builder->where('is_disabled', 0);
        });
    } // end of boot

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    } // end of category

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    } // end of user

    public function variations()
    {
        return $this->hasMany(Variation::class, "parent_id", "id");
    } // end of variations

    public function packages()
    {
        return $this->hasMany(Package::class, "parent_id", "id");
    } // end of packages

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, "transactions_lines", "transaction_id", "product_id")->withPivot("id", "variation_id", "discount_amount", "qty", "qty_returned", "tax_amount", "cost", "price", "tailoring_txt", "tailoring_custom","status");
    } // end of transactions

    public function quotations()
    {
        return $this->belongsToMany(QuotationsList::class, "quotation_list_lines", "header_id", "product_id");
    } // end of quotations

    public function stocks()
    {
        return $this->hasMany(Stock::class, "product_id", "id");
    } // end of stocks

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    } // end of unit

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id','id');
    } // end of brand

    public function location()
    {
        return $this->belongsTo(Location::class);
    } // end of location

    public function tailoringTypes()
    {
        return $this->belongsToMany(PackageType::class, "tailoring_package", "parent_id", "tailoring_type_id");
    } // end of tailoringTypes

    public function pricingGroups()
    {
        return $this->belongsToMany(PricingGroup::class, 'product_group_price', 'product_id', 'price_group_id')->withPivot('price', 'variant_id');
    }// end of pricingGroups

    public function QuotationListLines()
    {
        return $this->hasMany(QuotationsListLines::class, 'product_id', 'id');
    }
}
