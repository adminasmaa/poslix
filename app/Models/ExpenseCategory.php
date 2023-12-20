<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'expenses';

    public $timestamps = false;

    public function location()
    {
        return $this->belongsTo(Location::class);
    } // end of location

    public function expenses()
    {
        return $this->hasMany(Expense::class, "expense_id", "id");
    } // end of expenses

    public function transactions()
    {
        return $this->hasMany(TransactionExpenseCategory::class, "value", "id");
    } // end of transactions
}
