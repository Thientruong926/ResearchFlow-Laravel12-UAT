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
    Route::get('/', [GoogleAdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/settings', [GoogleAdminController::class, 'settings'])->name('admin.settings');
    Route::post('/settings/research', [GoogleAdminController::class, 'updateResearch'])->name('admin.settings.research');
    Route::post('/settings/schools', [GoogleAdminController::class, 'updateSchools'])->name('admin.settings.schools');
    Route::post('/settings/assignments', [GoogleAdminController::class, 'upsertAssignment'])->name('admin.settings.assignments');
    Route::post('/settings/assignments/{id}/delete', [GoogleAdminController::class, 'removeAssignment'])->name('admin.settings.assignments.delete');
    Route::get('/responses', [GoogleAdminController::class, 'responses'])->name('admin.responses');
    Route::get('/responses/export', [GoogleAdminController::class, 'export'])->name('admin.responses.export');
});
