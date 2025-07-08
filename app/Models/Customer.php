<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'mobile_no',
        'email',
        'address',
        'customer_code',
    ];

    public function deliveries()
    {
        return $this->hasMany(\App\Models\Delivery::class, 'customer_id');
    }

    public function addresses()
    {
        return $this->morphMany(\App\Models\Address::class, 'addressable');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
