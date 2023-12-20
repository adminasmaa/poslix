<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'business_locations';

    public function business(){
        return $this->belongsTo(Business::class, "business_id", "id");
    } // end of business

    public function user(){
        return $this->belongsTo(User::class, "owner_id", "id");
    } // end of user

    public function transactions(){
        return $this->hasMany(Transaction::class, "location_id", "id");
    } // end of transactions

    public function transferredTransactions(){
        return $this->hasMany(Transaction::class, "transferred_location_id", "id");
    } // end of transferredTransactions

    public function products(){
        return $this->hasMany(Product::class, "location_id", "id");
    } // end of products

    public function brands(){
        return $this->hasMany(Brand::class, "location_id", "id");
    } // end of brands

    public function packageTypes(){
        return $this->hasMany(PackageType::class, "location_id", "id");
    } // end of packageTypes

    public function categories(){
        return $this->hasMany(Category::class);
    } // end of categories

    public function taxes(){
        return $this->hasMany(Tax::class);
    } // end of taxes

    public function taxGroups(){
        return $this->hasMany(TaxGroup::class);
    } // end of taxGroups

    public function expenseCategories(){
        return $this->hasMany(Expense::class);
    } // end of expenses

    public function expenses(){
        return $this->hasMany(TransactionExpenseCategory::class);
    } // end of expenses

    public function printSetting(){
        return $this->hasMany(PrintSetting::class, "location_id", "location_id")
            ->select('name', 'connection', 'ip', 'print_type', 'status', 'location_id');
    } // end of printSetting

    public function tailoringExtras(){
        return $this->hasMany(TailoringExtra::class, "location_id", "id");
    } // end of TailoringExtra

    public function pricingGroups(){
        return $this->hasMany(PricingGroup::class, "location_id", "id");
    } // end of pricingGroups

    public function paymentMethods(){
        return $this->hasMany(PaymentMethod::class, "location_id", "id");
    } // end of paymentMethods
}
