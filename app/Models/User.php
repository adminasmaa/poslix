<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'user_type',
        'contact_number',
        'status',
        'email',
        'password',
        'owner_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
//        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
//        'password' => 'hashed',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'created_by', 'id');
    } // end of categories

    public function products()
    {
        return $this->hasMany(Product::class, 'created_by', 'id');
    } // end of products

    public function customers()
    {
        return $this->hasMany(Customer::class, 'created_by', 'id');
    } // end of customers

    public function locations()
    {
        return $this->hasMany(Location::class, 'owner_id', 'id');
    } // end of locations

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'created_by', 'id');
    } // end of transactions

    public function quotations()
    {
        return $this->hasMany(QuotationsList::class, 'employ_id', 'id');
    } // end of quotations

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'created_by', 'id');
    } // end of stocks

    public function brands(){
        return $this->hasMany(Brand::class, 'created_by', 'id');
    } // end of brands

    public function packageTypes(){
        return $this->hasMany(PackageType::class, 'created_by', 'id');
    } // end of packageTypes

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(role::class, 'user_roles', 'user_id', 'role_id')
            ->with(['locations'=>function($query){
                $query->select('business_locations.id', 'business_locations.name');
            }])
            ->with(['permissions'=>function($query){
                $query->select('permissions.id', 'permissions.name', 'url', 'method');
            }]);
    }
}
