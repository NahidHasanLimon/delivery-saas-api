<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;

class CompanyUser extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
    ];
    protected $hidden = ['password'];

    // JWTSubject methods:
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // Relationship:
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}