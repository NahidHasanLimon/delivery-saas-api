<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OTPVerification extends Model
{
    protected $table = 'o_t_p_verifications';

    protected $fillable = [
        'mobile_no',
        'email',
        'otp_code',
        'purpose',
        'user_type',
        'channel',
        'ref_id_or_context_id',
        'ip_address',
        'user_agent',
        'is_verified',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
