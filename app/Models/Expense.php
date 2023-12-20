<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'expenses_list';

    public $timestamps = false;
    
    public function location()
    {
        return $this->belongsTo(Location::class);
    } // end of location

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, "expense_id", "id");
    } // end of expenseCategory

}
