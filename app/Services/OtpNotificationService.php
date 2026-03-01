<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpNotificationService
{
    public function sendSmsOtp(string $mobileNo, string $otp): bool
    {
        // Placeholder for SMS integration.
        Log::info('OTP SMS sent', [
            'mobile_no' => $this->maskMobile($mobileNo),
            'otp' => $otp,
        ]);

        return true;
    }

    public function sendEmailOtp(string $email, string $otp): bool
    {
        try {
            Mail::raw("Your OTP code is: {$otp}", function ($message) use ($email) {
                $message->to($email)->subject('Your OTP Code');
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send OTP email', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendToBoth(?string $mobileNo, ?string $email, string $otp): array
    {
        $results = [
            'sms' => null,
            'email' => null,
        ];

        if ($mobileNo) {
            $results['sms'] = $this->sendSmsOtp($mobileNo, $otp);
        }

        if ($email) {
            $results['email'] = $this->sendEmailOtp($email, $otp);
        }

        return $results;
    }

    private function maskMobile(string $mobileNo): string
    {
        $visible = 3;
        $len = strlen($mobileNo);
        if ($len <= $visible) {
            return $mobileNo;
        }
        return str_repeat('*', $len - $visible) . substr($mobileNo, -$visible);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        $name = $parts[0];
        $domain = $parts[1];
        $masked = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 1, 0));
        return $masked . '@' . $domain;
    }
}
