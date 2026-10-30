<?php

namespace App\Providers;

use App\Contracts\SurveyResponseReader;
use App\Services\GoogleSheetsService;
use App\Services\ResearchRuntimePolicy;
use Illuminate\Support\ServiceProvider;

class ResearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SurveyResponseReader::class, GoogleSheetsService::class);
    }

    public function boot(ResearchRuntimePolicy $policy): void
    {
        $policy->assertValid();
    }
}
