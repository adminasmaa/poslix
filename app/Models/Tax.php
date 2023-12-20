<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = "tax_rates";

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function taxGroup()
    {
        return $this->belongsToMany(Tax::class, "tax_group", "parent_id", "tax_id");
    }

    public function user()
    {
        return $this->belongsTo(User::class, "created_by", "id");
    }
}
