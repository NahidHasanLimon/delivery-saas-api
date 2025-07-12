<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'unit',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the item.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the delivery items for this item.
     */
    public function deliveryItems()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    /**
     * Get the deliveries that contain this item.
     */
    public function deliveries()
    {
        return $this->belongsToMany(Delivery::class, 'delivery_items')
                    ->withPivot('quantity', 'notes')
                    ->withTimestamps();
    }

    /**
     * Scope to filter items by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter active items only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
