<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Rider;
use App\Services\OtpService;
use App\Services\OtpNotificationService;
use Illuminate\Support\Facades\Hash;

class RiderAuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'mobile_no' => 'required|string',
            'password' => 'required|string',
        ]);

        $rider = Rider::where('mobile_no', $request->mobile_no)->first();
        if (! $rider) {
            return $this->error('Invalid credentials.', [], 401);
        }
        if ($rider->status !== 'active') {
            return $this->error('Account is not active. Please complete activation.', [], 403);
        }

        if (! $token = Auth::guard('rider')->attempt($credentials)) {
            return $this->error('Invalid credentials.', [], 401);
        }

        $rider->last_login_at = now();
        $rider->save();

        return $this->respondWithToken($token);
    }

    // Logout
    public function logout()
    {
        Auth::guard('rider')->logout();
        return $this->success(null, 'Logged out successfully.');
    }

    // Refresh token
    public function refresh()
    {
        $token = Auth::guard('rider')->refresh();
        return $this->respondWithToken($token);
    }

    // Get current rider
    public function me()
    {
        return $this->success(Auth::guard('rider')->user(), 'Rider fetched.');
    }

    // Activate account with OTP
    public function activate(Request $request, OtpService $otpService)
    {
        $request->validate([
            'mobile_no' => 'required|string',
            'otp_code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $rider = Rider::where('mobile_no', $request->mobile_no)->first();
        if (! $rider) {
            return $this->error('Rider not found.', [], 404);
        }

        $verified = $otpService->verify(
            'rider_activation',
            $request->mobile_no,
            $rider->email,
            $request->otp_code
        );

        if (! $verified) {
            return $this->error('Invalid or expired OTP.', [], 422);
        }

        $rider->password = Hash::make($request->password);
        $rider->status = 'active';
        $rider->activated_at = now();
        $rider->save();

        return $this->success($rider, 'Account activated successfully.');
    }

    // Request OTP for password reset
    public function requestPasswordResetOtp(Request $request, OtpService $otpService, OtpNotificationService $otpNotifier)
    {
        $request->validate([
            'mobile_no' => 'required|string',
        ]);

        $rider = Rider::where('mobile_no', $request->mobile_no)->first();
        if (! $rider) {
            return $this->error('Rider not found.', [], 404);
        }

        $otp = $otpService->create(
            'rider_password_reset',
            $rider->mobile_no,
            $rider->email,
            'sms_email',
            $request->ip(),
            $request->userAgent()
        );

        $sent = $otpNotifier->sendToBoth($rider->mobile_no, $rider->email, $otp);

        return $this->success([
            'otp_sent' => $sent,
        ], 'Password reset OTP sent.');
    }

    // Reset password with OTP
    public function resetPassword(Request $request, OtpService $otpService)
    {
        $request->validate([
            'mobile_no' => 'required|string',
            'otp_code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $rider = Rider::where('mobile_no', $request->mobile_no)->first();
        if (! $rider) {
            return $this->error('Rider not found.', [], 404);
        }

        $verified = $otpService->verify(
            'rider_password_reset',
            $request->mobile_no,
            $rider->email,
            $request->otp_code
        );

        if (! $verified) {
            return $this->error('Invalid or expired OTP.', [], 422);
        }

        $rider->password = Hash::make($request->password);
        $rider->save();

        return $this->success(null, 'Password reset successfully.');
    }

    protected function respondWithToken($token)
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('rider')->factory()->getTTL() * 60,
        ], 'Login successful.');
    }
}
