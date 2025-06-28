<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompanyLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyUserAuthController extends Controller
{
    /**
     * Company user login.
     *
     * @param  CompanyLoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(CompanyLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! $token = Auth::guard('company_user')->attempt($credentials)) {
            return $this->error('Invalid credentials.', [], 401);
        }

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => Auth::guard('company_user')->factory()->getTTL() * 60,
            'user'         => Auth::guard('company_user')->user(),
        ], 'Login successful.');
    }

    /**
     * Get current authenticated company user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = Auth::guard('company_user')->user();
        return $this->success($user, 'Current user fetched.');
    }

    /**
     * Logout the current company user (invalidate token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        Auth::guard('company_user')->logout();
        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Refresh the JWT for the current company user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $token = Auth::guard('company_user')->refresh();
        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => Auth::guard('company_user')->factory()->getTTL() * 60,
        ], 'Token refreshed.');
    }
}