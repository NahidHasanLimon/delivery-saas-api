<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class SystemAdmin extends Authenticatable implements JWTSubject
{
    // JWTSubject methods:
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
