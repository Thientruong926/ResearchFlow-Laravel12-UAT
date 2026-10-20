<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
class AuthController extends Controller {
 public function show() {return view('auth.login');}
 public function login(Request $r) {
  $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
  $key='login:'.sha1(strtolower($data['email']).'|'.$r->ip());
  if(RateLimiter::tooManyAttempts($key,5)) return back()->withErrors(['email'=>'Quá nhiều lần thử. Thử lại sau.']);
  if(!Auth::attempt($data+['active'=>true])) {RateLimiter::hit($key,60);return back()->withErrors(['email'=>'Email hoặc mật khẩu không đúng.']);}
  RateLimiter::clear($key);$r->session()->regenerate();Audit::log('auth.login','users',Auth::id());return redirect()->intended(route('admin.dashboard'));
 }
 public function logout(Request $r) {Audit::log('auth.logout','users',Auth::id());Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect(route('home'));}
}
