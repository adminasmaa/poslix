<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected  $guarded = [];

    protected $table = "transaction_payments";

    public $timestamps = false;

    public function transaction(){
        return $this->belongsTo(Transaction::class, "transaction_id", "id");
    } // end of transaction

    public function quotation(){
        return $this->belongsTo(QuotationsList::class, "quotation_id", "id");
    } // end of transaction
}
