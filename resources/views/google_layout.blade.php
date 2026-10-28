<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ResearchFlow')</title>
    <style>
        :root{--navy:#0f2f4f;--blue:#1769aa;--pale:#eef6fb;--ink:#182533;--muted:#607286;--line:#d8e3ec;--ok:#237a4b;--bad:#b43c3c;--warn:#9a5b00}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink);background:#f4f7fa}a{color:var(--blue)}
        header{background:var(--navy);color:#fff}.wrap{max-width:1180px;margin:auto;padding:0 18px}.topbar{min-height:66px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{color:#fff;text-decoration:none;font-weight:800;font-size:20px}.brand span{color:#76c9ff}
        nav{display:flex;align-items:center;gap:18px;flex-wrap:wrap}nav a,nav button{color:#fff;text-decoration:none;background:none;border:0;padding:8px 0;font:inherit;cursor:pointer}nav form{margin:0}
        main{max-width:1180px;margin:30px auto;padding:0 18px 60px}.hero{background:linear-gradient(135deg,#0f2f4f,#1769aa);color:#fff;border-radius:22px;padding:42px;box-shadow:0 18px 45px #143a5b24}.hero h1{font-size:clamp(30px,5vw,48px);max-width:780px;margin:0 0 16px}.hero p{font-size:18px;line-height:1.65;max-width:820px;color:#e4f4ff}
        h1{font-size:30px}h2{font-size:22px;margin-top:0}h3{font-size:17px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;margin:18px 0;box-shadow:0 5px 18px #18334b0a}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}.grid .card{margin:0}.stat strong{display:block;font-size:32px;color:var(--navy)}
        label{display:block;font-weight:700;margin:14px 0 6px}input,select,textarea{width:100%;padding:11px 12px;border:1px solid #b9c8d6;border-radius:9px;background:#fff;font:inherit}textarea{min-height:110px}.inline-choice{display:flex;gap:12px;flex-wrap:wrap}.choice{border:1px solid var(--line);border-radius:10px;padding:12px 14px;font-weight:500;flex:1;min-width:160px}.choice input{width:auto;margin-right:7px}
        button,.btn{display:inline-block;border:0;border-radius:9px;padding:11px 17px;background:var(--blue);color:#fff;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.btn.secondary,button.secondary{background:#5f7184}.btn.danger,button.danger{background:#aa3e3e}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}
        .notice,.error,.success{padding:13px 15px;border-radius:10px;margin:14px 0}.notice{background:#eaf5ff;border-left:4px solid var(--blue)}.error{background:#fff0ef;border-left:4px solid var(--bad)}.success{background:#eaf8ef;border-left:4px solid var(--ok)}.muted{color:var(--muted)}.small{font-size:13px}.pill{display:inline-block;padding:5px 9px;border-radius:999px;background:#dcefff;color:#114f7e;font-size:12px;font-weight:800}.pill.ok{background:#dff4e7;color:#17623a}.pill.bad{background:#fee3df;color:#8e2929}
        table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:10px;border-bottom:1px solid #e3e9ef;vertical-align:top}th{background:#f0f5f9}.scroll{overflow:auto}.bar{height:10px;background:#e8eef4;border-radius:99px;overflow:hidden}.bar span{display:block;height:100%;background:var(--blue)}code{background:#eef2f5;padding:2px 5px;border-radius:4px}.question[hidden]{display:none}.required{color:var(--bad)}
        footer{text-align:center;color:var(--muted);padding:24px}.filters{display:grid;grid-template-columns:2fr repeat(3,1fr);gap:12px;align-items:end}.chart-row{display:grid;grid-template-columns:minmax(120px,1fr) 3fr 60px;gap:10px;align-items:center;margin:9px 0}
        @media(max-width:760px){.topbar{align-items:flex-start;flex-direction:column;padding-top:16px;padding-bottom:16px}nav{gap:12px}.hero{padding:27px 22px}.filters{grid-template-columns:1fr}.card{padding:18px}main{margin-top:20px}.chart-row{grid-template-columns:100px 1fr 42px}}
    </style>
</head>
<body>
<header><div class="wrap topbar"><a class="brand" href="{{ route('home') }}">Research<span>Flow</span></a><nav>
    <a href="{{ route('home') }}">Trang khảo sát</a>
    @if(session('admin_authenticated'))
        <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
        <a href="{{ route('admin.responses') }}">Phản hồi</a>
        <a href="{{ route('admin.settings') }}">Cài đặt</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button>Đăng xuất</button></form>
    @else
        <a href="{{ route('login') }}">Quản lý</a>
    @endif
</nav></div></header>
<main>
    @if(session('ok'))<div class="success">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="error"><strong>Chưa thể tiếp tục:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer>ResearchFlow · Dữ liệu phản hồi được gửi trực tiếp đến Google Forms và đồng bộ qua Google Sheets</footer>
</body>
</html>
