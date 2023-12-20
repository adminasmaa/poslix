<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = "contacts";

    protected  $guarded = [];

    public function user(){
        return $this->belongsTo(User::class, 'created_by', 'id');
    } // end of user

    public function transactions(){
        return $this->hasMany(Transaction::class, "contact_id", "id");
    } // end of transactions

    public function quotations(){
        return $this->hasMany(QuotationsList::class, "customer_id", "id");
    } // end of quotations

    public function pricingGroup(){
        return $this->belongsTo(PricingGroup::class, 'price_groups_id', 'id');
    } // end of pricingGroup
}
