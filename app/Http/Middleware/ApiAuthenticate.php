<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class ApiAuthenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // For API requests, do not redirect, just return null so an exception is thrown
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        // For web requests, use default behavior (could be customized)
        return route('login');
    }
}
