<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'order_number',
        'customer_id',
        'is_delivery_order',
        'order_source',
        'status',
        'delivery_status',
        'delivery_contact_name',
        'delivery_mobile_number',
        'delivery_address',
        'delivery_area',
        'delivery_latitude',
        'delivery_longitude',
        'subtotal_amount',
        'delivery_fee',
        'adjustment_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'paid_amount',
        'collectible_amount',
        'note',
        'internal_note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_delivery_order' => 'boolean',
        'subtotal_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'collectible_amount' => 'decimal:2',
        'delivery_latitude' => 'decimal:7',
        'delivery_longitude' => 'decimal:7',
        'deleted_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
}
