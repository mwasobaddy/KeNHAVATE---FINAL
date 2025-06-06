<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            
            // If user hasn't accepted terms, redirect to terms page
            if (!$user->terms_accepted) {
                // Don't redirect if already on terms page to prevent loop
                if (!$request->routeIs('terms-and-conditions')) {
                    return redirect()->route('terms-and-conditions');
                }
            }
        }

        return $next($request);
    }
}
