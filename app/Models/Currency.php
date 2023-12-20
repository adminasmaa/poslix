<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, "currency_id", "id");
    } // end of transactions

    public function transactionExpenseCategories()
    {
        return $this->hasMany(TransactionExpenseCategory::class, "currency_id", "id");
    } // end of transactionExpenseCategories
}
