<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Landlord
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user has admin role
        if ($user->role !== 'landlord') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Tenant privileges required.'
            ], 403);
        }
        return $next($request);
    }
}
