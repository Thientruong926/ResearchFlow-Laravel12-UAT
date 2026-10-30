@extends('layout') @section('content')
    <h1>
        Người dùng và phân quyền</h1>
    <div class="card">
        <h2>Tạo tài khoản cá nhân</h2>
        <p class="muted">Không dùng chung tài khoản. Owner hoặc IT_ADMIN cấp tài khoản theo phân công đã duyệt.</p>
        <form method="POST" action="{{ route('admin.users.add') }}">@csrf<div class="grid">
                <div><label>Tên hiển thị</label><input name="name" required></div>
                <div><label>Email quản trị</label><input name="email" type="email" required></div>
                <div><label>Mật khẩu (≥12 ký tự)</label><input name="password" type="password" required minlength="12"></div>
                <div><label>Vai trò</label><select name="role">
                        @foreach (['owner', 'researcher', 'quota_manager', 'qc_reviewer', 'data_manager', 'viewer', 'NCS', 'IT_ADMIN', 'ANALYST', 'SITE_COORDINATOR', 'AUDITOR'] as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Cơ sở phụ trách (bắt buộc cho SITE_COORDINATOR)</label><select name="assigned_site_id"><option value="">Không áp dụng</option>@foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->code }}</option>@endforeach</select></div>
            </div><button>Tạo tài khoản</button></form>
    </div>
    <div class="card">
        <h2>Danh sách tài khoản</h2>
        <table>
            <tr>
                <th>Email</th>
                <th>Tên</th>
                <th>Vai trò</th>
                <th>Trạng thái</th>
                <th>Quản lý</th>
            </tr>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->role }}</td>
                    <td>{{ $user->active ? 'Đang hoạt động' : 'Đã khóa' }}</td>
                    <td>
                      @if ($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.toggle', $user->id) }}">@csrf
                          <button class="alt" onclick="return confirm('Xác nhận thay đổi trạng thái tài khoản?')">
                            {{ $user->active ? 'Khóa tài khoản' : 'Mở khóa' }}
                          </button>
                        </form>
                      @else
                        Tài khoản hiện tại
                      @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
