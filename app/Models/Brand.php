<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['products_count'];

    public function products()
    {
        return $this->hasMany(Product::class);
    } // end of products

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    } // end of user

    public function location()
    {
        return $this->belongsTo(Location::class);
    } // end of location

    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    } // end of getProductsCountAttribute
}
