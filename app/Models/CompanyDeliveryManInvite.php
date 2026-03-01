<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDeliveryManInvite extends Model
{
    protected $fillable = [
        'company_id',
        'mobile_number',
        'delivery_man_id',
        'status',
        'created_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveryMan()
    {
        return $this->belongsTo(DeliveryMan::class);
    }
}

