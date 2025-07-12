<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'delivery_id',
        'item_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the delivery that owns the delivery item.
     */
    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * Get the item that belongs to the delivery item.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the company that owns the delivery item.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
