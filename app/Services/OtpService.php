<?php

namespace App\Services;

use App\Models\OTPVerification;
use Illuminate\Support\Carbon;

class OtpService
{
    public function create(
        string $purpose,
        ?string $mobileNo,
        ?string $email,
        string $channel,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $ttlMinutes = 10,
        string $userType = 'delivery_man'
    ): string {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = hash('sha256', $otp);

        OTPVerification::create([
            'mobile_no' => $mobileNo,
            'email' => $email,
            'otp_code' => $otpHash,
            'purpose' => $purpose,
            'user_type' => $userType,
            'channel' => $channel,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'is_verified' => false,
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
        ]);

        return $otp;
    }

    public function verify(
        string $purpose,
        ?string $mobileNo,
        ?string $email,
        string $otp
    ): bool {
        $query = OTPVerification::query()
            ->where('purpose', $purpose)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now());

        if ($mobileNo) {
            $query->where('mobile_no', $mobileNo);
        }

        if ($email) {
            $query->where('email', $email);
        }

        $record = $query->orderByDesc('id')->first();

        if (! $record) {
            return false;
        }

        $otpHash = hash('sha256', $otp);
        if (! hash_equals($record->otp_code, $otpHash)) {
            return false;
        }

        $record->is_verified = true;
        $record->verified_at = Carbon::now();
        $record->save();

        return true;
    }
}
