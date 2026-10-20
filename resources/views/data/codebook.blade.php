@extends('layout') @section('content')
    <h1>
        Codebook và mapping câu hỏi → biến</h1>
    <p>Biến xuất theo quy ước <code>Q{survey_id}_{code}</code>; mỗi phiên bản bảng hỏi giữ ID riêng, không làm mất dấu nguồn.</p>
    <p><a class="btn" href="{{ route('data.codebook.csv') }}">Tải codebook CSV UTF-8</a></p>
    <div class="card scroll">
        <table>
            <tr>
                <th>Biến SPSS</th>
                <th>Nhóm</th>
                <th>Version</th>
                <th>Nhãn biến</th>
                <th>Loại</th>
                <th>Value labels</th>
                <th>Missing</th>
                <th>Thang đo</th>
            </tr>
            @foreach ($questions as $q)
                <tr>
                    <td><code>Q{{ $q->survey_id }}_{{ strtoupper($q->code) }}</code></td>
                    <td>{{ $q->group_code }}</td>
                    <td>{{ $q->version }}</td>
                    <td>{{ $q->variable_label ?? $q->label }}</td>
                    <td>{{ $q->type }}</td>
                    <td>{{ $q->choices }}</td>
                    <td>{{ $q->missing_code }}</td>
                    <td>{{ $q->scale }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
