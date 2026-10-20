@extends('layout') @section('content')
    <h1>
        QC session #{{ $session->id }}</h1>
    <div class="card">
        <h2>Cờ kỹ thuật đã phát hiện</h2>
        <table>
            <tr>
                <th>Quy tắc</th>
                <th>Minh chứng tối thiểu</th>
                <th>Định nghĩa</th>
            </tr>
            @forelse($flags as $flag)
                <tr>
                    <td>{{ $flag->code }}</td>
                    <td>{{ $flag->evidence }}</td>
                    <td>{{ $flag->definition }}</td>
            </tr>@empty<tr>
                    <td colspan="3">Không có cờ</td>
                </tr>
            @endforelse
        </table>
    </div>
    <div class="card">
        <h2>Lịch sử quyết định QC</h2>
        <table>
            <tr>
                <th>Thời gian</th>
                <th>Kết quả</th>
                <th>Lý do</th>
                <th>Người duyệt</th>
            </tr>
            @foreach ($decisions as $d)
                <tr>
                    <td>{{ $d->created_at }}</td>
                    <td>{{ $d->decision }}</td>
                    <td>{{ $d->reason }}</td>
                    <td>{{ $d->email }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
