<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ResearchFlow' }}</title>
    <style>
        :root {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: #162436;
            background: #f4f7fb
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0
        }

        header {
            background: #0f2743;
            color: white;
            padding: 15px 20px
        }

        header a {
            color: #fff;
            text-decoration: none
        }


        header .wrap {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 14px;
            max-width: 1400px;
            margin: 0 auto;
        }

        header .wrap>strong {
            font-size: 17px;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 10px 18px;
            font-size: 14px;
        }

        nav a {
            display: inline-block;
            padding: 7px 4px;
            line-height: 1.5;
            white-space: nowrap;
        }

        nav a:hover {
            color: #a9d7ff;
        }

        nav form {
            display: inline-flex;
            align-items: center;
            margin: 0;
        }

        nav button {
            margin: 0;
        }

        @media (max-width: 768px) {
            header {
                padding: 14px 16px;
            }

            nav {
                gap: 8px 12px;
                font-size: 13px;
            }

            nav a {
                padding: 6px 2px;
            }
        }

        main {
            max-width: 1180px;
            margin: 28px auto;
            padding: 0 16px 50px
        }

        h1 {
            font-size: 28px;
            margin: 4px 0 18px
        }

        h2 {
            font-size: 21px
        }

        p {
            line-height: 1.55
        }

        .card {
            background: #fff;
            border: 1px solid #dce4ed;
            border-radius: 13px;
            padding: 22px;
            margin: 15px 0;
            box-shadow: 0 2px 10px #16243609
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px
        }

        .grid .card {
            margin: 0
        }

        .muted {
            color: #53657b
        }

        .pill {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 8px;
            background: #e8eff8;
            color: #193f64;
            font-size: 12px
        }

        label {
            display: block;
            font-weight: 600;
            margin: 12px 0 5px
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #bac8d7;
            border-radius: 8px;
            padding: 10px;
            font: inherit;
            background: white
        }

        input[type=checkbox],
        input[type=radio] {
            width: auto;
            margin-right: 7px
        }

        textarea {
            min-height: 90px
        }

        button,
        .btn {
            display: inline-block;
            border: 0;
            background: #1464ad;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font: inherit;
            padding: 10px 15px;
            cursor: pointer;
            margin: 7px 5px 4px 0
        }

        .btn.alt,
        button.alt {
            background: #54667c
        }

        .btn.warn,
        button.warn {
            background: #a84c22
        }

        .btn:disabled,
        button:disabled {
            opacity: .5
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px
        }

        td,
        th {
            padding: 9px;
            border-bottom: 1px solid #e2e8ee;
            text-align: left;
            vertical-align: top
        }

        th {
            background: #f5f8fb
        }

        table a {
            color: #07518b
        }

        .scroll {
            overflow-x: auto
        }

        .notice {
            border-left: 4px solid #1773b0;
            background: #eaf5ff;
            padding: 12px;
            margin: 12px 0
        }

        .error {
            border-left: 4px solid #be3e3e;
            background: #fff0ef;
            padding: 12px;
            margin: 10px 0
        }

        .success {
            border-left: 4px solid #248551;
            background: #edfaf0;
            padding: 12px;
            margin: 10px 0
        }

        .actions {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap
        }

        .small {
            font-size: 13px
        }

        .bad {
            color: #a52a2a
        }

        summary {
            cursor: pointer
        }

        code {
            background: #eef1f5;
            padding: 2px 5px
        }

        footer {
            padding: 24px;
            text-align: center;
            color: #627489;
            font-size: 12px
        }
    </style>
</head>

<body>
    <header>
        <div class="wrap"><strong><a href="{{ route('home') }}">◈ ResearchFlow</a></strong>
            <nav><a href="{{ route('home') }}">Trang chủ</a>@auth<a href="{{ route('admin.dashboard') }}">Tổng quan</a>
                    @if(auth()->user()->hasRole('owner','researcher','NCS','IT_ADMIN'))<a href="{{ route('admin.sites') }}">Điểm mẫu</a>@endif
                    @if(auth()->user()->hasRole('owner','researcher','quota_manager'))<a href="{{ route('admin.quota') }}">Quota</a>@endif
                    @if(auth()->user()->hasRole('owner','researcher','NCS'))<a href="{{ route('admin.surveys') }}">Khảo sát</a><a href="{{ route('admin.information') }}">Thông tin/Consent</a><a href="{{ route('admin.requests') }}">Yêu cầu</a>@endif
                    @if(auth()->user()->hasRole('owner','researcher','qc_reviewer'))<a href="{{ route('qc.index') }}">QC</a>@endif
                    @if(auth()->user()->hasRole('owner','researcher','qc_reviewer','NCS'))<a href="{{ route('admin.integrity') }}">Cờ trùng kỹ thuật</a>@endif
                    @if(auth()->user()->hasRole('owner','researcher','data_manager'))<a href="{{ route('data.index') }}">Data/SPSS</a>@endif
                    @if(auth()->user()->hasRole('ANALYST','NCS'))<a href="{{ route('data.long.raw') }}">Xuất dữ liệu nghiên cứu</a><a href="{{ route('data.research.codebook') }}">Codebook CSV</a>@endif
                    @if(auth()->user()->hasRole('SITE_COORDINATOR'))<a href="{{ route('admin.site.status') }}">Điểm được phân công</a>@endif
                    @if(auth()->user()->hasRole('owner','IT_ADMIN'))<a href="{{ route('admin.users') }}">Người dùng</a>@endif
                    @if(auth()->user()->hasRole('owner','researcher','NCS','IT_ADMIN','AUDITOR'))<a href="{{ route('admin.research.audit') }}">Nhật ký truy cập</a>@endif
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button style="background:transparent;padding:0">Đăng
                    xuất</button></form>@else<a href="{{ route('login') }}">Đăng nhập</a>@endauth
        </nav>
    </div>
</header>
<main>
    @if (session('ok'))
        <div class="success">{{ session('ok') }}</div>
        @endif @if ($errors->any())
            <div class="error"><strong>Dữ liệu chưa hợp lệ:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif @yield('content')
</main>
<footer>ResearchFlow · Chỉ vận hành nghiên cứu chính thức sau khi được phê duyệt · Không thu họ tên/MSSV trong khảo sát</footer>
</body>

</html>
