<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

use Tymon\JWTAuth\Contracts\JWTSubject;

class DeliveryMan extends Authenticatable implements JWTSubject
{
    use HasFactory;
      protected $hidden = ['password'];

      protected $fillable = [
        'name',
        'email',
        'mobile_no',
        'password',
        // add other fields as needed
    ];
    // JWTSubject methods:
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function companies()
    {
        return $this->belongsToMany(\App\Models\Company::class, 'company_delivery_man');
    }
}
