<?php

namespace App\Http\Controllers\DeliveryMan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DeliveryMan;
use App\Services\OtpService;
use App\Services\OtpNotificationService;
use Illuminate\Support\Facades\Hash;

class DeliveryManAuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'mobile_no' => 'required|string',
            'password' => 'required|string',
        ]);

        $deliveryMan = DeliveryMan::where('mobile_no', $request->mobile_no)->first();
        if (! $deliveryMan) {
            return $this->error('Invalid credentials.', [], 401);
        }
        if ($deliveryMan->status !== 'active') {
            return $this->error('Account is not active. Please complete activation.', [], 403);
        }

        if (! $token = Auth::guard('delivery_man')->attempt($credentials)) {
            return $this->error('Invalid credentials.', [], 401);
        }

        $deliveryMan->last_login_at = now();
        $deliveryMan->save();

        return $this->respondWithToken($token);
    }

    // Logout
    public function logout()
    {
        Auth::guard('delivery_man')->logout();
        return $this->success(null, 'Logged out successfully.');
    }

    // Refresh token
    public function refresh()
    {
        $token = Auth::guard('delivery_man')->refresh();
        return $this->respondWithToken($token);
    }

    // Get current delivery man
    public function me()
    {
        return $this->success(Auth::guard('delivery_man')->user(), 'Delivery man fetched.');
    }

    // Activate account with OTP
    public function activate(Request $request, OtpService $otpService)
    {
        $request->validate([
            'mobile_no' => 'required|string',
            'otp_code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $deliveryMan = DeliveryMan::where('mobile_no', $request->mobile_no)->first();
        if (! $deliveryMan) {
            return $this->error('Delivery man not found.', [], 404);
        }

        $verified = $otpService->verify(
            'delivery_man_activation',
            $request->mobile_no,
            $deliveryMan->email,
            $request->otp_code
        );

        if (! $verified) {
            return $this->error('Invalid or expired OTP.', [], 422);
        }

        $deliveryMan->password = Hash::make($request->password);
        $deliveryMan->status = 'active';
        $deliveryMan->activated_at = now();
        $deliveryMan->save();

        return $this->success($deliveryMan, 'Account activated successfully.');
    }

    // Request OTP for password reset
    public function requestPasswordResetOtp(Request $request, OtpService $otpService, OtpNotificationService $otpNotifier)
    {
        $request->validate([
            'mobile_no' => 'required|string',
        ]);

        $deliveryMan = DeliveryMan::where('mobile_no', $request->mobile_no)->first();
        if (! $deliveryMan) {
            return $this->error('Delivery man not found.', [], 404);
        }

        $otp = $otpService->create(
            'delivery_man_password_reset',
            $deliveryMan->mobile_no,
            $deliveryMan->email,
            'sms_email',
            $request->ip(),
            $request->userAgent()
        );

        $sent = $otpNotifier->sendToBoth($deliveryMan->mobile_no, $deliveryMan->email, $otp);

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

        $deliveryMan = DeliveryMan::where('mobile_no', $request->mobile_no)->first();
        if (! $deliveryMan) {
            return $this->error('Delivery man not found.', [], 404);
        }

        $verified = $otpService->verify(
            'delivery_man_password_reset',
            $request->mobile_no,
            $deliveryMan->email,
            $request->otp_code
        );

        if (! $verified) {
            return $this->error('Invalid or expired OTP.', [], 422);
        }

        $deliveryMan->password = Hash::make($request->password);
        $deliveryMan->save();

        return $this->success(null, 'Password reset successfully.');
    }

    protected function respondWithToken($token)
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('delivery_man')->factory()->getTTL() * 60,
        ], 'Login successful.');
    }
}
