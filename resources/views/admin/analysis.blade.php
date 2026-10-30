@extends('layout')
@section('content')
<h1>Dữ liệu nghiên cứu</h1>
<div class="card"><p>Bản dành cho ANALYST chỉ gồm phiên hoàn tất đã được QC chấp nhận và không đang rút lui. Mã thiếu 97, 98, BLANK được giữ riêng.</p>
<p><a href="{{ route('data.long.raw') }}">Tải dữ liệu dạng dài CSV</a> · <a href="{{ route('data.research.codebook') }}">Tải codebook CSV</a></p></div>
@endsection
