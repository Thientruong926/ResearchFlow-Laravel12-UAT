<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ResearchFlow')</title>
    <style>
        :root{--navy:#102a43;--navy-2:#173f5f;--blue:#1479b8;--pale:#eef8fc;--ink:#182533;--muted:#607286;--line:#d8e3ec;--ok:#237a4b;--bad:#b43c3c;--warn:#9a5b00;--shadow:0 12px 30px rgba(21,54,79,.08)}
        *{box-sizing:border-box}html,body{overflow-x:hidden}body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink);background:linear-gradient(180deg,#f6fafc 0,#f4f7fa 40%,#edf3f7 100%);min-height:100vh}a{color:var(--blue)}
        a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid var(--blue);outline-offset:2px}
        header{background:linear-gradient(120deg,var(--navy),var(--navy-2));color:#fff;box-shadow:0 4px 18px rgba(16,42,67,.18)}.wrap{max-width:1180px;margin:auto;padding:0 18px}.topbar{min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{color:#fff;text-decoration:none;font-weight:800;font-size:21px;letter-spacing:-.3px}.brand span{color:#6fe0e0}
        nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap}nav a,nav button{color:#eaf8ff;text-decoration:none;background:none;border:0;border-radius:8px;padding:9px 11px;font:inherit;cursor:pointer}nav a:hover,nav button:hover{background:rgba(255,255,255,.12);color:#fff}nav form{margin:0}
        main{max-width:1180px;margin:34px auto;padding:0 18px 60px}.hero{position:relative;overflow:hidden;background:linear-gradient(125deg,#102a43 0%,#1769aa 58%,#25a3ad 100%);color:#fff;border-radius:24px;padding:48px;box-shadow:0 18px 45px rgba(20,58,91,.18)}.hero:after{content:"";position:absolute;width:260px;height:260px;border:1px solid rgba(255,255,255,.2);border-radius:50%;right:-70px;top:-90px}.hero h1{font-size:clamp(30px,5vw,48px);line-height:1.12;max-width:780px;margin:0 0 16px;position:relative;z-index:1}.hero p{font-size:18px;line-height:1.65;max-width:820px;color:#e4f4ff;position:relative;z-index:1}
        h1{font-size:30px}h2{font-size:22px;margin-top:0}h3{font-size:17px}.card{background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:16px;padding:24px;margin:18px 0;box-shadow:var(--shadow)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}.grid .card{margin:0}.stat{position:relative;overflow:hidden}.stat:after{content:"";position:absolute;width:90px;height:90px;border-radius:50%;right:-34px;bottom:-38px;background:var(--pale)}.stat strong{display:block;font-size:32px;color:var(--navy);margin-top:7px;position:relative;z-index:1}
        label{display:block;font-weight:700;margin:14px 0 6px}input,select,textarea{width:100%;padding:11px 12px;border:1px solid #b9c8d6;border-radius:9px;background:#fff;font:inherit;transition:border-color .15s,box-shadow .15s}input:focus,select:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(20,121,184,.12);outline:0}textarea{min-height:110px}.inline-choice{display:flex;gap:12px;flex-wrap:wrap}.choice{border:1px solid var(--line);border-radius:10px;padding:12px 14px;font-weight:500;flex:1;min-width:160px;transition:border-color .15s,background .15s,box-shadow .15s,transform .15s}.choice:hover{border-color:var(--blue);background:var(--pale)}.choice.is-selected{border-color:#13a6a6;background:#e4fbf7;box-shadow:0 0 0 2px rgba(19,166,166,.18);color:#075b62}.choice.is-selected input{accent-color:#079b9b}.choice input{width:auto;margin-right:7px}.question.is-answered{border-color:#7acbca;background:linear-gradient(180deg,#fff 0,#f1fcfb 100%);box-shadow:0 10px 24px rgba(19,140,145,.12)}.question.is-answered>label:first-child:after{content:'Đã trả lời';display:inline-block;margin-left:10px;padding:3px 8px;border-radius:999px;background:#d9f6f1;color:#087576;font-size:11px;font-weight:800;vertical-align:middle}
        button,.btn{display:inline-block;border:0;border-radius:9px;padding:11px 17px;background:var(--blue);color:#fff;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.btn.secondary,button.secondary{background:#5f7184}.btn.danger,button.danger{background:#aa3e3e}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}
        .notice,.error,.success{padding:13px 15px;border-radius:10px;margin:14px 0}.notice{background:#eaf5ff;border-left:4px solid var(--blue)}.error{background:#fff0ef;border-left:4px solid var(--bad)}.success{background:#eaf8ef;border-left:4px solid var(--ok)}.muted{color:var(--muted)}.small{font-size:13px}.pill{display:inline-block;padding:5px 9px;border-radius:999px;background:#dcefff;color:#114f7e;font-size:12px;font-weight:800}.pill.ok{background:#dff4e7;color:#17623a}.pill.bad{background:#fee3df;color:#8e2929}
        table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:10px;border-bottom:1px solid #e3e9ef;vertical-align:top}th{background:#f0f5f9}.scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}.bar{height:10px;background:#e8eef4;border-radius:99px;overflow:hidden}.bar span{display:block;height:100%;background:var(--blue)}code{background:#eef2f5;padding:2px 5px;border-radius:4px}.question[hidden]{display:none}.required{color:var(--bad)}
        footer{text-align:center;color:var(--muted);padding:24px}.filters{display:grid;grid-template-columns:2fr repeat(3,1fr);gap:12px;align-items:end}.chart-row{display:grid;grid-template-columns:minmax(120px,1fr) 3fr 60px;gap:10px;align-items:center;margin:9px 0}
        @media(max-width:760px){.topbar{align-items:flex-start;flex-direction:column;padding-top:16px;padding-bottom:16px}nav{gap:4px;width:100%}nav a,nav form,nav button{padding:10px 8px 10px 0}.hero{padding:29px 22px}.filters{grid-template-columns:1fr}.card{padding:18px}main{margin-top:20px;padding:0 12px 40px}.chart-row{grid-template-columns:100px 1fr 42px}button,.btn{min-height:44px;display:inline-flex;align-items:center;justify-content:center}input,select,textarea{font-size:16px}}
    </style>
</head>
<body>
<header><div class="wrap topbar"><a class="brand" href="{{ route('home') }}">Research<span>Flow</span></a><nav>
    <a href="{{ route('home') }}">Trang khảo sát</a>
    @if(session('admin_authenticated'))
        <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
        <a href="{{ route('admin.connections') }}">Kết nối</a>
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
