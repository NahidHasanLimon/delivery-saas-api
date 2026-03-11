<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'company_id',
        'order_id',
        'delivery_source',
        'delivery_man_id',
        'customer_id',
        'tracking_number',
        
        // Pickup address fields
        'pickup_address_id',
        'pickup_label',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        
        // Drop address fields
        'drop_address_id',
        'drop_label',
        'drop_address',
        'drop_latitude',
        'drop_longitude',
        
        'delivery_notes',
        'expected_delivery_time',
        'delivery_method',
        'provider_name',
        'status',
        'assigned_at',
        'picked_at',
        'delivered_at',
        'cancelled_at',
        'in_progress_at',
        'collectible_amount',
        'collected_amount',
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

    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class, 'order_id');
    }

    public function deliveryMan()
    {
        return $this->belongsTo(\App\Models\DeliveryMan::class, 'delivery_man_id');
    }

    public function pickupAddress()
    {
        return $this->belongsTo(\App\Models\Address::class, 'pickup_address_id');
    }

    public function dropAddress()
    {
        return $this->belongsTo(\App\Models\Address::class, 'drop_address_id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * Get the delivery items for this delivery.
     */
    public function deliveryItems()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    /**
     * Get the items for this delivery.
     */
    public function items()
    {
        return $this->belongsToMany(Item::class, 'delivery_items')
                    ->withPivot('item_name', 'unit', 'unit_price', 'quantity', 'line_total', 'notes')
                    ->withTimestamps();
    }

    /**
     * Get formatted delivery items with clean structure
     */
    public function getFormattedItemsAttribute()
    {
        return $this->deliveryItems->map(function ($deliveryItem) {
            return [
                'item_id' => $deliveryItem->item_id,
                'name' => $deliveryItem->item_name,
                'unit' => $deliveryItem->unit,
                'unit_price' => $deliveryItem->unit_price,
                'quantity' => $deliveryItem->quantity,
                'line_total' => $deliveryItem->line_total,
                'notes' => $deliveryItem->notes,
            ];
        });
    }
}
