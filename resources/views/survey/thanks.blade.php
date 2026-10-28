@extends('google_layout')
@section('title', 'Cảm ơn bạn')
@section('content')
<section class="card" style="max-width:680px;margin:60px auto;text-align:center;padding:45px">
    <div style="font-size:60px;color:#237a4b">✓</div>
    <h1>Cảm ơn bạn đã hoàn thành khảo sát</h1>
    <p class="muted">Câu trả lời đã được gửi đến Google Forms. Website ResearchFlow không lưu bản sao trong database.</p>
    <a class="btn" href="{{ route('home') }}">Trở về trang giới thiệu</a>
</section>
@endsection
