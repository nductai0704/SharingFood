<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Kiểm tra xem role của user có nằm trong danh sách được phép không
        if (!in_array($user->role, $roles)) {
            // Nếu không được phép, tự động điều hướng về trang tương ứng của role đó
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'charity') {
                return $user->status === 'verified'
                    ? redirect()->route('charity.dashboard')
                    : redirect()->route('charity.pending');
            } else {
                return redirect('/');
            }
        }

        return $next($request);
    }
}
