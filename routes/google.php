<?php

use App\Http\Controllers\GoogleAdminController;
use App\Http\Controllers\GoogleSurveyController;
use App\Http\Controllers\SessionAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GoogleSurveyController::class, 'home'])->name('home');
Route::post('/survey/start', [GoogleSurveyController::class, 'start'])->name('survey.start');
Route::get('/survey', [GoogleSurveyController::class, 'show'])->name('survey.show');
Route::post('/survey', [GoogleSurveyController::class, 'submit'])->middleware('throttle:12,1')->name('survey.submit');
Route::get('/thank-you', [GoogleSurveyController::class, 'thanks'])->name('survey.thanks');

Route::get('/login', [SessionAuthController::class, 'show'])->name('login');
Route::post('/login', [SessionAuthController::class, 'login'])->middleware('throttle:8,1')->name('login.submit');
Route::post('/logout', [SessionAuthController::class, 'logout'])->name('logout');

Route::middleware('admin.session')->prefix('admin')->group(function () {
    Route::get('/connections', [\App\Http\Controllers\AdminConnectionController::class, 'index'])->name('admin.connections');
    Route::post('/connections/{source}/check', [\App\Http\Controllers\AdminConnectionController::class, 'check'])->middleware('throttle:60,1')->name('admin.connections.check');
    Route::post('/connections/{source}/verification', [\App\Http\Controllers\AdminConnectionController::class, 'verification'])->middleware('throttle:20,1')->name('admin.connections.verification');
    Route::get('/', [GoogleAdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/settings', [GoogleAdminController::class, 'settings'])->name('admin.settings');
    Route::get('/settings/research', [\App\Http\Controllers\AdminResearchSettingsController::class, 'edit'])->name('admin.settings.research.edit');
    Route::post('/settings/research/preview', [\App\Http\Controllers\AdminResearchSettingsController::class, 'preview'])->middleware('throttle:30,1')->name('admin.settings.research.preview');
    Route::post('/settings/research', [\App\Http\Controllers\AdminResearchSettingsController::class, 'save'])->middleware('throttle:20,1')->name('admin.settings.research');
    Route::post('/settings/columns', [GoogleAdminController::class, 'updateColumns'])->middleware('throttle:20,1')->name('admin.settings.columns');
    Route::post('/settings/schools', [GoogleAdminController::class, 'updateSchools'])->middleware('throttle:20,1')->name('admin.settings.schools');
    Route::post('/settings/assignments', [GoogleAdminController::class, 'upsertAssignment'])->middleware('throttle:20,1')->name('admin.settings.assignments');
    Route::post('/settings/assignments/{id}/delete', [GoogleAdminController::class, 'removeAssignment'])->middleware('throttle:20,1')->name('admin.settings.assignments.delete');
    Route::get('/responses', [\App\Http\Controllers\AdminResponseController::class, 'index'])->name('admin.responses');
    Route::get('/responses/export', [\App\Http\Controllers\AdminResponseController::class, 'export'])->middleware('throttle:12,1')->name('admin.responses.export');
    Route::get('/responses/detail', [\App\Http\Controllers\AdminResponseController::class, 'detail'])->name('admin.responses.detail');
});
