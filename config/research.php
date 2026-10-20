<?php
return [
 'minor_flow' => (bool) env('RESEARCH_MINOR_FLOW', false),
 'collection_open' => (bool) env('RESEARCH_COLLECTION_OPEN', false),
 'audio_approved' => (bool) env('RESEARCH_AUDIO_APPROVED', false),
 'dashboard_min_cell' => max(2,(int) env('RESEARCH_DASHBOARD_MIN_CELL',5)),
 'schedule_snapshots' => (bool) env('RESEARCH_SCHEDULE_SNAPSHOTS', false),
 'groups' => ['manager'=>'Cán bộ quản lý', 'lecturer'=>'Giảng viên', 'student'=>'Sinh viên'],
];
