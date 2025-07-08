<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'addressable_id',
        'addressable_type',
        'address_type',
        'label',
        'address',
        'latitude',
        'longitude',
    ];

    /**
     * Get the owning addressable model.
     */
    public function addressable()
    {
        return $this->morphTo();
    }

    /**
     * Get the company that owns the address.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to filter addresses by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter addresses by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('address_type', $type);
    }
}
