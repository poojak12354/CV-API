<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
         
        $user = Auth::user();

        if($user->hasRole('team')!=true){

            return response()->json([
                'error' => "Please login from Team Admin"
            ], 406);
        }
         
        return $next($request);
    }
}
