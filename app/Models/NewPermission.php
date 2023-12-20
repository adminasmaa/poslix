<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewPermission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = "permissions";

    public function roles()
    {
        return $this->belongsToMany(role::class, 'role_permissions', 'permission_id', 'role_id');
    }
}
