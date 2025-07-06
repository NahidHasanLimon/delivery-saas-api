<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'company_id',
        'delivery_man_id',
        'customer_id',
        'tracking_number',
        'delivery_address',
        'latitude',
        'longitude',
        'delivery_notes',
        'delivery_type',
        'expected_delivery_time',
        'delivery_mode',
        'assigned_at',
        'delivered_at',
        'amount', // ensure amount is fillable
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($delivery) {
            $delivery->tracking_number = static::generateTrackingNumber();
        });
    }

    /**
     * Generate a unique tracking number
     */
    private static function generateTrackingNumber()
    {
        $prefix = 'DL';
        $date = now()->format('Ymd');
        
        // Get the last delivery for today to increment the sequence
        $lastDelivery = static::where('tracking_number', 'like', $prefix . $date . '%')
            ->orderBy('tracking_number', 'desc')
            ->first();

        if ($lastDelivery) {
            $lastSequence = (int) substr($lastDelivery->tracking_number, -3);
            $sequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }

        return $prefix . $date . $sequence;
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    public function deliveryMan()
    {
        return $this->belongsTo(\App\Models\DeliveryMan::class, 'delivery_man_id');
    }
}
