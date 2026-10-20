<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\{AuthController,AdminController,PortalController,QcController,DataController,CleaningRuleController};
Route::get('/',[PortalController::class,'home'])->name('home');
Route::get('/login',[AuthController::class,'show'])->middleware('guest')->name('login');
Route::post('/login',[AuthController::class,'login'])->middleware('guest')->name('login.submit');
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
// Survey participant access by unguessable invitation/resume links, not admin sessions.
Route::get('/join/{token}',[PortalController::class,'intro'])->name('portal.intro');
Route::post('/join/{token}',[PortalController::class,'join'])->middleware('throttle:8,1')->name('portal.join');
Route::get('/s/{resume}',[PortalController::class,'survey'])->name('survey.fill');
Route::post('/s/{resume}',[PortalController::class,'save'])->middleware('throttle:30,1')->name('survey.save');
Route::get('/s/{resume}/support',[PortalController::class,'support'])->name('portal.support');
Route::post('/s/{resume}/support',[PortalController::class,'request'])->middleware('throttle:8,1')->name('portal.request');
Route::middleware(['auth','active'])->prefix('admin')->group(function(){
  Route::get('/',[AdminController::class,'dashboard'])->name('admin.dashboard');
  Route::get('/dashboard/snapshot',[AdminController::class,'snapshot'])->middleware('role:owner,researcher,quota_manager')->name('admin.snapshot');
  Route::middleware('role:owner,researcher')->group(function(){
    Route::get('/consent-preview', function () {
    abort_unless(app()->environment('local'), 404);

    $info = DB::table('information_versions')
        ->where('status', 'published')
        ->orderByDesc('version')
        ->first();

    $survey = DB::table('surveys')
        ->where('group_code', 'student')
        ->where('status', 'published')
        ->orderByDesc('version')
        ->first();

    abort_unless($info && $survey, 404);

    $link = (object) [
        'group_code' => 'student',
        'token' => '',
    ];

    return view('portal.intro', [
        'link' => $link,
        'info' => $info,
        'survey' => $survey,
        'previewMode' => true,
    ]);
})->name('admin.consent.preview');
    Route::get('/sites',[AdminController::class,'sites'])->name('admin.sites');Route::post('/sites',[AdminController::class,'addSite'])->name('admin.site.add');Route::post('/waves',[AdminController::class,'addWave'])->name('admin.wave.add');Route::post('/links',[AdminController::class,'addLink'])->name('admin.link.add');Route::post('/links/{id}/toggle',[AdminController::class,'toggleLink'])->name('admin.link.toggle');Route::get('/links/{id}/qr',[AdminController::class,'qr'])->name('admin.link.qr');
    Route::get('/information',[AdminController::class,'information'])->name('admin.information');Route::post('/information',[AdminController::class,'addInformation'])->name('admin.information.add');Route::post('/information/{id}/publish',[AdminController::class,'publishInformation'])->name('admin.information.publish');
    Route::get('/surveys',[AdminController::class,'surveys'])->name('admin.surveys');Route::post('/surveys',[AdminController::class,'addSurvey'])->name('admin.surveys.add');Route::get('/surveys/{id}',[AdminController::class,'surveyEdit'])->name('admin.survey.edit');Route::post('/surveys/{id}/questions',[AdminController::class,'addQuestion'])->name('admin.survey.question.add');Route::post('/surveys/{id}/questions/{questionId}/edit',[AdminController::class,'updateQuestion'])->name('admin.survey.question.update');Route::post('/surveys/{id}/questions/{questionId}/delete',[AdminController::class,'deleteQuestion'])->name('admin.survey.question.delete');Route::post('/surveys/{id}/clone',[AdminController::class,'cloneSurvey'])->name('admin.survey.clone');Route::post('/surveys/{id}/publish',[AdminController::class,'publishSurvey'])->name('admin.survey.publish');
  });
  Route::middleware('role:owner,researcher,quota_manager')->group(function(){Route::get('/quota',[AdminController::class,'quota'])->name('admin.quota');Route::post('/quota',[AdminController::class,'addQuota'])->name('admin.quota.add');Route::post('/quota/{id}/submit',[AdminController::class,'submitQuota'])->name('admin.quota.submit');});
  Route::post('/quota/{id}/approve',[AdminController::class,'approveQuota'])->middleware('role:owner,researcher')->name('admin.quota.approve');
  Route::middleware('role:owner,researcher,qc_reviewer')->group(function(){Route::get('/qc',[QcController::class,'index'])->middleware('audit_read')->name('qc.index');Route::get('/qc/{id}/flags',[QcController::class,'flags'])->middleware('audit_read')->name('qc.flags');Route::post('/qc/{id}/decide',[QcController::class,'decide'])->name('qc.decide');});
  Route::post('/qc/rules',[QcController::class,'addRule'])->middleware('role:owner,qc_reviewer')->name('qc.rule.add');
  Route::post('/qc/rules/{id}/approve',[QcController::class,'approveRule'])->middleware('role:owner,researcher')->name('qc.rule.approve');
  Route::middleware('role:owner,researcher,data_manager')->group(function(){Route::get('/data/cleaning-rules',[CleaningRuleController::class,'index'])->middleware('audit_read')->name('data.rules');Route::post('/data/cleaning-rules',[CleaningRuleController::class,'create'])->name('data.rule.create');Route::get('/data',[DataController::class,'index'])->middleware('audit_read')->name('data.index');Route::get('/data/codebook',[DataController::class,'codebook'])->middleware('audit_read')->name('data.codebook');Route::get('/data/codebook.csv',[DataController::class,'codebookCsv'])->middleware('audit_read')->name('data.codebook.csv');Route::get('/data/transcripts/export',[DataController::class,'transcriptExport'])->middleware('audit_read')->name('data.transcript.export');Route::get('/data/validate',[DataController::class,'validation'])->middleware('audit_read')->name('data.validate');Route::post('/data/export',[DataController::class,'export'])->name('data.export');Route::post('/data/cleaning',[DataController::class,'logCleaning'])->name('data.cleaning');Route::post('/data/transcripts',[DataController::class,'transcript'])->name('data.transcript');Route::post('/data/transcripts/{id}/transcribe',[DataController::class,'transcribe'])->name('data.transcript.transcribe');});
  Route::middleware('role:owner,researcher')->group(function(){Route::get('/dashboard/reports',[AdminController::class,'reportSnapshots'])->middleware('audit_read')->name('admin.reports');Route::get('/dashboard/reports/{id}',[AdminController::class,'downloadReport'])->name('admin.report.download');Route::post('/data/transcripts/{id}/review',[DataController::class,'reviewTranscript'])->name('data.transcript.review');Route::post('/data/cleaning-rules/{id}/approve',[CleaningRuleController::class,'approve'])->name('data.rule.approve');Route::post('/data/cleaning-rules/{id}/retire',[CleaningRuleController::class,'retire'])->name('data.rule.retire');Route::get('/requests',[AdminController::class,'requests'])->middleware('audit_read')->name('admin.requests');Route::post('/requests/{id}/resolve',[AdminController::class,'resolveRequest'])->name('admin.requests.resolve');});
  Route::middleware('role:owner')->group(function(){Route::get('/users',[AdminController::class,'users'])->name('admin.users');Route::post('/users',[AdminController::class,'addUser'])->name('admin.users.add');Route::post('/users/{id}/toggle',[AdminController::class,'toggleUser'])->name('admin.users.toggle');Route::get('/audit',[AdminController::class,'audit'])->name('admin.audit');});
});
