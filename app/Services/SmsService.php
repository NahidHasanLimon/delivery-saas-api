<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendOtp(string $mobileNumber, string $otp): bool
    {
        // Stub implementation; replace with real SMS gateway integration.
        Log::info('Invite OTP sent via SMS', [
            'mobile_no' => $mobileNumber,
            'otp' => $otp,
        ]);

        return true;
    }
}

