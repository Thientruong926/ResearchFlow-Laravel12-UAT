@extends('google_layout')
@section('title', 'Đăng nhập quản lý')
@section('content')
<section class="card" style="max-width:460px;margin:50px auto">
    <h1>Đăng nhập quản lý</h1>
    <p class="muted">Tài khoản được cấu hình trong file <code>.env</code>; không dùng database.</p>
    <form method="POST" action="{{ route('login.submit') }}">@csrf
        <label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
        <label for="password">Mật khẩu</label><input id="password" type="password" name="password" autocomplete="current-password" required>
        <div class="actions"><button>Đăng nhập</button></div>
    </form>
</section>
@endsection
