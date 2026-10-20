@extends('layout') @section('content')
    <h1>
        Audit trail và lịch sử thay đổi</h1>
    <div class="card scroll">
        <table>
            <tr>
                <th>Thời gian</th>
                <th>Tài khoản</th>
                <th>Hành động</th>
                <th>Đối tượng</th>
                <th>Metadata (không chứa câu trả lời)</th>
            </tr>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->created_at }}</td>
                    <td>{{ $log->email ?? 'participant/system' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->entity }} #{{ $log->entity_id }}</td>
                    <td>{{ $log->metadata }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
