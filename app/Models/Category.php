<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $guarded=[];

    protected $appends = ['products_count'];

    public function user(){
        return $this->belongsTo(User::class, 'created_by', 'id');
    } // end of user

    public function products()
    {
        return $this->hasMany(Product::class);
    } // end of products

    public function location()
    {
        return $this->belongsTo(Location::class);
    } // end of location

    public function subcategories()
    {
        return $this->hasMany(Category::class, "parent_id", "id");
    } // end of subcategories

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, "parent_id", "id");
    } // end of parentCategory

    public function childrenCategories()
    {
        return $this->hasMany(Category::class, "parent_id", "id");
    } // end of childrenCategories

    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    } // end of getProductsCountAttribute
}
