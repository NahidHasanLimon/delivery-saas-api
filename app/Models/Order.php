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
        'order_type',
        'delivery_medium',
        'status',
        'delivery_status',
        'delivery_contact_name',
        'delivery_mobile_number',
        'delivery_address',
        'delivery_area',
        'delivery_latitude',
        'delivery_longitude',
        'amount',
        'payment_method',
        'payment_status',
        'paid_amount',
        'collectible_amount',
        'note',
        'internal_note',
        'assigned_delivery_man_id',
        'created_by',
        'updated_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
