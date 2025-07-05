<?php

namespace App\Http\Controllers\DeliveryMan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DeliveryMan;

class DeliveryManAuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if (!$token = Auth::guard('delivery_man')->attempt($credentials)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        return $this->respondWithToken($token);
    }

    // Logout
    public function logout()
    {
        Auth::guard('delivery_man')->logout();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
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
        return response()->json(['success' => true, 'data' => Auth::guard('delivery_man')->user()]);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('delivery_man')->factory()->getTTL() * 60,
        ]);
    }
}
