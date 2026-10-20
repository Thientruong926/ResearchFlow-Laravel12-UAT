@extends('layout') @section('content')
    <h1>Yêu cầu hỗ trợ hoặc rút lui</h1>
    <div class="card">
        <p>Research ID: <code>{{ $person->research_id }}</code></p>
        <p>Việc rút lui và xử lý dữ liệu tuân theo phiếu thông tin nghiên cứu và quy trình được duyệt. Gửi yêu cầu không đồng nghĩa với xóa tự động mọi bản ghi.</p>
        <form method="POST" action="{{ route('portal.request', $resume) }}">@csrf<label>Loại yêu cầu</label><select name="type">
                <option value="support">Hỗ trợ</option>
                <option value="withdrawal">Yêu cầu rút lui</option>
            </select><label>Mô tả (không ghi thông tin định danh không cần thiết)</label>
            <textarea name="message" required maxlength="3000"></textarea><button>Gửi yêu cầu</button>
        </form>
    </div>
@endsection
