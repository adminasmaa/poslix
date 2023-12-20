<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionExpenseCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'expenses_values';
    
    public $timestamps = false;

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    } // end of transaction

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, "value", "id");
    } // end of expenseCategory

    public function location()
    {
        return $this->belongsTo(Location::class);
    } // end of location

    public function currency()
    {
        return $this->belongsTo(Currency::class, "currency_id", "id");
    } // end of currency
}
