<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function location()
    {
        return $this->belongsTo(Location::class, "location_id", "id");
    } // end of location

    public function transferredLocation()
    {
        return $this->belongsTo(Location::class, "transferred_location_id", "id");
    } // end of transferredLocation

    public function user()
    {
        return $this->belongsTo(User::class, "created_by", "id");
    } // end of user

    public function customer()
    {
        return $this->belongsTo(Customer::class, "contact_id", "id");
    } // end of customer

    public function payment()
    {
        return $this->hasMany(Payment::class, "transaction_id", "id");
    } // end of payment

    public function products()
    {
        return $this->belongsToMany(Product::class, "transactions_lines", "transaction_id", "product_id")->withPivot('id', "variation_id", "discount_amount", "qty", "qty_returned", "tax_amount", "cost", "price", "tailoring_txt", "tailoring_custom", "status");
    } // end of products

    public function stocks()
    {
        return $this->hasMany(Stock::class, "transaction_id", "id");
    } // end of stocks

    public function currency()
    {
        return $this->belongsTo(Currency::class, "currency_id", "id");
    } // end of currency

    public function expenseCategories()
    {
        return $this->hasMany(TransactionExpenseCategory::class, "transaction_id", "id");
    } // end of transactionExpenseCategories

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, "supplier_id", "id");
    } // end of supplier
}
