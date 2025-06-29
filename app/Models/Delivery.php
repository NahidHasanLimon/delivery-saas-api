<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'company_id',
        'delivery_man_id',
        'customer_id',
        'delivery_address',
        'latitude',
        'longitude',
        'delivery_notes',
        'delivery_type',
        'expected_delivery_time',
        'delivery_mode',
        'assigned_at',
        'delivered_at',
        'details',
    ];

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    public function deliveryMan()
    {
        return $this->belongsTo(\App\Models\DeliveryMan::class, 'delivery_man_id');
    }
}
