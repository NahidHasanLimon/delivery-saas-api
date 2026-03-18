<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRiderInvite extends Model
{
    protected $fillable = [
        'company_id',
        'mobile_number',
        'rider_id',
        'status',
        'created_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}

