<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('research:check', function () {
    $this->info('ResearchFlow: '.(config('research.collection_open') ? 'Collection switch ON' : 'Collection switch OFF'));
    $this->info('Minor flow: '.(config('research.minor_flow') ? 'prototype enabled' : 'disabled'));
});

// Run once from a trusted scheduler. Files stay on the PRIVATE local disk (never public/storage).
\Illuminate\Support\Facades\Artisan::command('research:snapshot', function () {
    [$csv,$rows]=\App\Services\SnapshotService::build();
    $path='snapshots/quota_qc_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.csv';
    if(!\Illuminate\Support\Facades\Storage::disk('local')->put($path,$csv))throw new \RuntimeException('Không thể lưu snapshot riêng tư.');
    \Illuminate\Support\Facades\DB::table('report_snapshots')->insert(['storage_path'=>$path,'quota_rows'=>$rows,'sha256'=>hash('sha256',$csv),'created_at'=>now()]);
    $this->info('Saved private snapshot: '.$path.' ('.$rows.' approved quotas).');
});
// Explicit opt-in; server administrator must configure the Laravel scheduler/cron.
\Illuminate\Support\Facades\Schedule::command('research:snapshot')->dailyAt('08:00')->when(fn()=>config('research.schedule_snapshots',false));
