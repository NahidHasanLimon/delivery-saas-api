<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    //
    public function deliveryMen()
    {
        return $this->belongsToMany(\App\Models\DeliveryMan::class, 'company_delivery_man');
    }
    public function deliveries()
    {
        return $this->hasMany(\App\Models\Delivery::class,'company_id');
    }
    public function customers()
    {
        return $this->hasMany(\App\Models\Customer::class, 'company_id');
    }
    public function companyUsers()
    {
        return $this->hasMany(\App\Models\CompanyUser::class, 'company_id');
    }
    public function activityLogs()
    {
        return $this->hasMany(\App\Models\CompanyActivityLog::class, 'company_id');
    }

    public function addresses()
    {
        return $this->hasMany(\App\Models\Address::class, 'company_id');
    }

    public function items()
    {
        return $this->hasMany(\App\Models\Item::class, 'company_id');
    }
}
