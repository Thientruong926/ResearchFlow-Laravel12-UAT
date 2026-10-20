@extends('layout') @section('content')
    <div class="card" style="max-width:440px;margin:auto">
        <h1>Đăng nhập</h1>
        <form action="{{ route('login.submit') }}" method="POST">@csrf<label>Email</label><input name="email" type="email" autocomplete="username"
                value="{{ old('email') }}" required><label>Mật khẩu</label><input name="password" type="password" autocomplete="current-password"
                required><button>Đăng nhập</button></form>
    </div>
@endsection
