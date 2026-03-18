<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

use Tymon\JWTAuth\Contracts\JWTSubject;

class Rider extends Authenticatable implements JWTSubject
{
    use HasFactory;
      protected $hidden = ['password'];

    protected $fillable = [
        'name',
        'email',
        'mobile_no',
        'identification_number',
        'password',
        'status',
        'invited_at',
        'activated_at',
        'last_login_at',
        // add other fields as needed
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_login_at' => 'datetime',
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
        return $this->belongsToMany(\App\Models\Company::class, 'company_rider')
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    public function deliveries()
    {
        return $this->hasMany(\App\Models\Delivery::class);
    }
}
