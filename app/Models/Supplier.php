<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected  $guarded = [];

    public function location(){
        return $this->belongsTo(location::class, 'location_id', 'id');
    }
    public function transaction(){
        return $this->hasMany(Transaction::class, 'supplier_id', 'id');
    }
    public function quotation(){
        return $this->hasMany(QuotationsList::class, 'supplier_id', 'id');
    }

}
