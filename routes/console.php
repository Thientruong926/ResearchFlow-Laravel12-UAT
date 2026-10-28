<?php

use App\Services\ResearchSettings;
use Illuminate\Support\Facades\Artisan;

Artisan::command('research:check', function (ResearchSettings $settings) {
    $assignments = $settings->assignments();
    $active = collect($assignments)->where('active', true)->count();

    $this->info('Storage mode: Google Forms + Google Sheets (no database)');
    $this->info('Google sync: '.(config('research.google_sync_enabled') ? 'ON' : 'OFF'));
    $this->info('Schools: '.count($settings->schools()));
    $this->info("Assignments: {$active} active / ".count($assignments).' configured');
});
