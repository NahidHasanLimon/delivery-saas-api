<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'mobile_no',
        'address',
    ];

    public function deliveries()
    {
        return $this->hasMany(\App\Models\Delivery::class, 'customer_id');
    }
}
