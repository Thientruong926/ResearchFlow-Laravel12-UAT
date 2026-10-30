<?php

return [
    'google_sync_enabled' => (bool) env('GOOGLE_SYNC_ENABLED', false),
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.test'),
        'password' => env('ADMIN_PASSWORD', ''),
    ],
    'settings_file' => storage_path('app/research-settings.json'),
    'connections' => [
        'metadata_directory' => storage_path('app/private/connection-health'),
        'stale_seconds' => 300,
        'max_bytes' => 10 * 1024 * 1024,
    ],
    'responses' => [
        'max_rows' => 10000,
        'max_columns' => 100,
        'max_bytes' => 10 * 1024 * 1024,
        'reference_ttl' => 600,
    ],
    'groups' => [
        'manager' => 'Cán bộ quản lý',
        'lecturer' => 'Giảng viên',
        'student' => 'Sinh viên',
    ],
    'default_settings' => [
        'source_defaults' => [
            'timestamp_format' => 'd/m/Y H:i:s',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ],
        'research' => [
            'title' => 'Khảo sát ứng dụng công nghệ số trong dạy học',
            'introduction' => 'Khảo sát phục vụ nghiên cứu về hoạt động ứng dụng công nghệ số trong dạy học cho sinh viên ngành Giáo dục mầm non tại các trường đại học ở Thành phố Hồ Chí Minh.',
            'contact_name' => 'Nhóm nghiên cứu',
            'contact_email' => 'research@example.test',
            'contact_phone' => '',
            'contact_address' => '',
            'consent' => 'Việc tham gia hoàn toàn tự nguyện. Không nhập họ tên, mã số sinh viên, số điện thoại hoặc thông tin nhận dạng cá nhân.',
        ],
        'schools' => [
            ['code' => 'SITE_01', 'name' => 'Trường đại học minh họa 01', 'targets' => ['manager' => 10, 'lecturer' => 30, 'student' => 100]],
        ],
        'assignments' => [],
        'sheet_columns' => [
            'timestamp' => 'Dấu thời gian',
            'school' => 'Mã trường',
            'group' => 'Nhóm đối tượng',
        ],
    ],
];
