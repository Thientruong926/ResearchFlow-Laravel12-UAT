<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SessionAuthController extends Controller
{
    public function show(Request $request)
    {
        if ($request->session()->get('admin_authenticated') === true) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.session_login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:'.sha1(strtolower($credentials['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Quá nhiều lần thử. Vui lòng thử lại sau một phút.']);
        }

        $configuredEmail = strtolower((string) config('research.admin.email'));
        $configuredPassword = (string) config('research.admin.password');
        $valid = $configuredPassword !== ''
            && hash_equals($configuredEmail, strtolower($credentials['email']))
            && hash_equals($configuredPassword, $credentials['password']);

        if (! $valid) {
            RateLimiter::hit($key, 60);
            Log::warning('Admin login failed', ['email' => $credentials['email'], 'ip' => $request->ip()]);
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put([
            'admin_authenticated' => true,
            'admin_email' => $configuredEmail,
        ]);
        Log::info('Admin login succeeded', ['email' => $configuredEmail]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Log::info('Admin logout', ['email' => $request->session()->get('admin_email')]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
