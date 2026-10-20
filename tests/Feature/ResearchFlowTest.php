<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ResearchFlowTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role='owner'): User
{
    $user = User::create([
        'name' => 'Test '.$role,
        'email' => $role.Str::random(5).'@example.test',
        'password' => 'temporary-password-123',
        'role' => $role,
    ]);

    $user->active = true;
    $user->save();

    return $user;
}
 private function publishedProject():array {
  $site=DB::table('sites')->insertGetId(['code'=>'SITE_TEST','created_at'=>now(),'updated_at'=>now()]);$wave=DB::table('waves')->insertGetId(['code'=>'WAVE_TEST','created_at'=>now(),'updated_at'=>now()]);
  $info=DB::table('information_versions')->insertGetId(['version'=>1,'purpose'=>'Approved purpose','activities'=>'Approved activities','duration'=>'Approved duration','voluntary'=>'Voluntary','withdrawal'=>'Withdraw according to protocol','contact'=>'Research team','data_use'=>'Approved usage','status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  $survey=DB::table('surveys')->insertGetId(['group_code'=>'student','version'=>1,'title'=>'Approved instrument','status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  $qid=DB::table('questions')->insertGetId(['survey_id'=>$survey,'code'=>'Q1','label'=>'Example approved question','type'=>'single','choices'=>'{"1":"Yes","2":"No"}','required'=>true,'position'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $token=Str::random(48);DB::table('sample_links')->insert(['site_id'=>$site,'wave_id'=>$wave,'group_code'=>'student','token'=>$token,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);return compact('site','wave','info','survey','qid','token');
 }
 public function test_public_portal_is_closed_by_default():void {$this->get('/')->assertOk()->assertSee('Chưa mở thu thập');$project=$this->publishedProject();$this->get('/join/'.$project['token'])->assertNotFound();}
 public function test_viewer_cannot_edit_scientific_configuration(): void
{
    $viewer = $this->user('viewer');

    $this->actingAs($viewer)
         ->get('/admin/surveys')
         ->assertForbidden();
}

public function test_viewer_can_access_dashboard(): void
{
    $viewer = $this->user('viewer');

    $this->actingAs($viewer)
         ->get('/admin')
         ->assertOk();
}
 public function test_participant_consent_version_and_submission_are_immutable():void {
  config(['research.collection_open'=>true]);$p=$this->publishedProject();$this->get('/join/'.$p['token'])->assertOk();
  $this->post('/join/'.$p['token'],['agree'=>'1','age_group'=>'adult','information_version_id'=>$p['info'],'survey_id'=>$p['survey']])->assertRedirect();$session=DB::table('survey_sessions')->first();$this->assertNotNull($session);$this->assertDatabaseHas('consents',['participant_id'=>$session->participant_id,'information_version_id'=>$p['info'],'decision'=>'agreed']);
  $url='/s/'.$session->resume_token;$this->post($url,['action'=>'complete','q_'.$p['qid']=>'1'])->assertRedirect();$this->assertDatabaseHas('survey_sessions',['id'=>$session->id,'status'=>'complete']);$this->assertDatabaseHas('answers',['survey_session_id'=>$session->id,'question_id'=>$p['qid']]);
  $old=DB::table('answers')->first()->value;$this->post($url,['action'=>'complete','q_'.$p['qid']=>'2'])->assertRedirect();$this->assertSame($old,DB::table('answers')->first()->value);
 }
 public function test_under_18_flow_is_blocked_without_approved_setting():void {config(['research.collection_open'=>true,'research.minor_flow'=>false]);$p=$this->publishedProject();$this->post('/join/'.$p['token'],['age_group'=>'minor','agree'=>'1','information_version_id'=>$p['info'],'survey_id'=>$p['survey']])->assertSessionHasErrors('age_group');$this->assertDatabaseCount('participants',0);}
 public function test_quota_submitter_cannot_approve_own_request():void {
  $owner=$this->user();$p=$this->publishedProject();$id=DB::table('quota_configs')->insertGetId(['site_id'=>$p['site'],'wave_id'=>$p['wave'],'group_code'=>'student','target'=>10,'status'=>'pending_review','submitted_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);$this->actingAs($owner)->post('/admin/quota/'.$id.'/approve')->assertForbidden();$this->assertDatabaseHas('quota_configs',['id'=>$id,'status'=>'pending_review']);
 }
 public function test_clean_export_excludes_unreviewed_but_raw_keeps_it():void {
   config(['research.collection_open'=>true]);$p=$this->publishedProject();$this->post('/join/'.$p['token'],['agree'=>'1','age_group'=>'adult','information_version_id'=>$p['info'],'survey_id'=>$p['survey']]);$session=DB::table('survey_sessions')->first();$this->post('/s/'.$session->resume_token,['action'=>'complete','q_'.$p['qid']=>'1']);$manager=$this->user('data_manager');
   $this->actingAs($manager)->post('/admin/data/export',['scope'=>'raw','format'=>'csv'])->assertOk()->assertDownload();$this->actingAs($manager)->post('/admin/data/export',['scope'=>'clean','format'=>'csv'])->assertOk()->assertDownload();$this->assertDatabaseHas('export_runs',['scope'=>'clean','rows'=>0]);$this->assertDatabaseHas('export_runs',['scope'=>'raw','rows'=>1]);
 }
 
public function test_inactive_link_blocks_join_requests(): void
{
    config(['research.collection_open' => true]);

    $p = $this->publishedProject();

    DB::table('sample_links')
        ->where('token', $p['token'])
        ->update(['active' => false]);

    $this->get('/join/' . $p['token'])
        ->assertNotFound();

    $this->post('/join/' . $p['token'], [
        'agree' => '1',
        'age_group' => 'adult',
        'information_version_id' => $p['info'],
        'survey_id' => $p['survey'],
    ])->assertNotFound();

    $this->assertDatabaseCount('participants', 0);
    $this->assertDatabaseCount('consents', 0);
    $this->assertDatabaseCount('survey_sessions', 0);
}

public function test_join_rejects_mismatched_published_version_ids(): void
{
    config(['research.collection_open' => true]);

    $p = $this->publishedProject();

    // Truong hop 1: ID thong tin nghien cuu sai.
    $this->post('/join/' . $p['token'], [
        'agree' => '1',
        'age_group' => 'adult',
        'information_version_id' => $p['info'] + 999,
        'survey_id' => $p['survey'],
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('agree');

    // Truong hop 2: ID bang hoi sai.
    $this->post('/join/' . $p['token'], [
        'agree' => '1',
        'age_group' => 'adult',
        'information_version_id' => $p['info'],
        'survey_id' => $p['survey'] + 999,
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('agree');

    // Khong tao du lieu khi ID khong hop le.
    $this->assertDatabaseCount('participants', 0);
    $this->assertDatabaseCount('consents', 0);
    $this->assertDatabaseCount('survey_sessions', 0);
}

public function test_raw_export_does_not_duplicate_session_with_multiple_consents(): void
{
    // Chi mo cong thu thap trong moi truong kiem thu.
    config(['research.collection_open' => true]);

    $p = $this->publishedProject();

    // Tao participant, consent va survey session gia.
    $this->post('/join/' . $p['token'], [
        'agree' => '1',
        'age_group' => 'adult',
        'information_version_id' => $p['info'],
        'survey_id' => $p['survey'],
    ])->assertRedirect();

    $session = DB::table('survey_sessions')->first();
    $this->assertNotNull($session);

    // Hoan thanh bang hoi.
    $this->post('/s/' . $session->resume_token, [
        'action' => 'complete',
        'q_' . $p['qid'] => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('survey_sessions', [
        'id' => $session->id,
        'status' => 'complete',
    ]);

    // Tao them consent gia cho cung participant.
    DB::table('consents')->insert([
        'participant_id' => $session->participant_id,
        'information_version_id' => $p['info'],
        'decision' => 'agreed',
        'guardian_permission' => false,
        'assent' => false,
        'recorded_at' => now()->subMinute(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->assertDatabaseCount('consents', 2);
    $this->assertDatabaseCount('survey_sessions', 1);

    // Xuat du lieu raw.
    $manager = $this->user('data_manager');

    $response = $this->actingAs($manager)
        ->post('/admin/data/export', [
            'scope' => 'raw',
            'format' => 'csv',
        ]);

    $response->assertOk()->assertDownload();

    // Chi duoc xuat mot dong cho mot survey session.
    $this->assertDatabaseHas('export_runs', [
        'scope' => 'raw',
        'format' => 'csv',
        'rows' => 1,
    ]);

    // Kiem tra noi dung CSV co dung mot dong du lieu.
    $handle = fopen('php://temp', 'w+');
    fwrite($handle, $response->getContent());
    rewind($handle);

    $csvRows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $csvRows[] = $row;
    }

    fclose($handle);

    // Mot dong header + mot dong du lieu.
    $this->assertCount(2, $csvRows);
}

public function test_raw_export_uses_consent_version_at_session_start(): void
{
    config(['research.collection_open' => true]);

    $p = $this->publishedProject();

    $this->post('/join/' . $p['token'], [
        'agree' => '1',
        'age_group' => 'adult',
        'information_version_id' => $p['info'],
        'survey_id' => $p['survey'],
    ])->assertRedirect();

    $session = DB::table('survey_sessions')->first();
    $this->assertNotNull($session);

    $this->post('/s/' . $session->resume_token, [
        'action' => 'complete',
        'q_' . $p['qid'] => '1',
    ])->assertRedirect();

    // Tao phien ban thong tin thu hai (du lieu gia).
    $info2 = DB::table('information_versions')->insertGetId([
        'version' => 2,
        'purpose' => 'Test purpose version 2',
        'activities' => 'Test activities',
        'duration' => 'Test duration',
        'voluntary' => 'Voluntary',
        'withdrawal' => 'Test withdrawal',
        'contact' => 'Test contact',
        'data_use' => 'Test usage',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Consent moi duoc ghi nhan sau khi session bat dau.
    DB::table('consents')->insert([
        'participant_id' => $session->participant_id,
        'information_version_id' => $info2,
        'decision' => 'agreed',
        'guardian_permission' => false,
        'assent' => false,
        'recorded_at' => now()->addMinute(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $manager = $this->user('data_manager');

    $response = $this->actingAs($manager)
        ->post('/admin/data/export', [
            'scope' => 'raw',
            'format' => 'csv',
        ]);

    $response->assertOk()->assertDownload();

    $handle = fopen('php://temp', 'w+');
    fwrite($handle, $response->getContent());
    rewind($handle);

    $headers = fgetcsv($handle);
    $row = fgetcsv($handle);
    $extraRow = fgetcsv($handle);

    fclose($handle);

    $this->assertIsArray($headers);
    $this->assertIsArray($row);
    $this->assertFalse($extraRow);

    $data = array_combine($headers, $row);

    $this->assertSame((string) $p['info'], $data['INFO_VERSION']);
}


 public function test_privacy_headers_on_participant_and_admin_pages():void {
  $p=$this->publishedProject();
  config(['research.collection_open'=>true]);
  $participant=$this->get('/join/'.$p['token']);
  $participant->assertOk()->assertHeader('Referrer-Policy','no-referrer')->assertHeader('X-Frame-Options','DENY');
  $this->assertStringContainsString('no-store',$participant->headers->get('Cache-Control'));
  $admin=$this->actingAs($this->user('viewer'))->get('/admin');
  $admin->assertOk();$this->assertStringContainsString('no-store',$admin->headers->get('Cache-Control'));
 }
 public function test_closing_collection_prevents_saving_existing_draft():void {
  $p=$this->publishedProject();config(['research.collection_open'=>true]);
  $this->post('/join/'.$p['token'],['agree'=>'1','age_group'=>'adult','information_version_id'=>$p['info'],'survey_id'=>$p['survey']])->assertRedirect();
  $session=DB::table('survey_sessions')->first();config(['research.collection_open'=>false]);
  $this->post('/s/'.$session->resume_token,['action'=>'complete','q_'.$p['qid']=>'1'])->assertForbidden();
  $this->assertDatabaseHas('survey_sessions',['id'=>$session->id,'status'=>'draft']);
  $this->assertDatabaseCount('answers',0);
 }
 public function test_published_survey_can_be_cloned_without_modifying_source():void {
  $p=$this->publishedProject();$owner=$this->user();
  $this->actingAs($owner)->post('/admin/surveys/'.$p['survey'].'/clone')->assertRedirect();
  $clone=DB::table('surveys')->where('id','!=',$p['survey'])->first();
  $this->assertNotNull($clone);$this->assertSame('draft',$clone->status);
  $this->assertSame(2,(int)$clone->version);
  $this->assertDatabaseHas('questions',['survey_id'=>$clone->id,'code'=>'Q1']);
  $this->assertDatabaseHas('surveys',['id'=>$p['survey'],'status'=>'published']);
 }
 public function test_invalid_branch_condition_is_rejected():void {
  $p=$this->publishedProject();$owner=$this->user();
  $draft=DB::table('surveys')->insertGetId(['group_code'=>'student','version'=>2,'title'=>'Draft','status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($owner)->post('/admin/surveys/'.$draft.'/questions',[
     'code'=>'Q2','label'=>'Wrong branch','type'=>'text','condition_code'=>'Q1','condition_value'=>'unknown'
  ])->assertSessionHasErrors('condition_code');
  DB::table('questions')->insert(['survey_id'=>$draft,'code'=>'Q1','label'=>'Root','type'=>'single','choices'=>'{"1":"Yes","2":"No"}','position'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($owner)->post('/admin/surveys/'.$draft.'/questions',[
     'code'=>'Q2','label'=>'Wrong branch','type'=>'text','condition_code'=>'Q1','condition_value'=>'unknown'
  ])->assertSessionHasErrors('condition_value');
  $this->assertDatabaseMissing('questions',['survey_id'=>$draft,'code'=>'Q2']);
 }
 public function test_withdrawal_holds_valid_session_from_clean_export():void {
  config(['research.collection_open'=>true]);$p=$this->publishedProject();
  $this->post('/join/'.$p['token'],['agree'=>'1','age_group'=>'adult','information_version_id'=>$p['info'],'survey_id'=>$p['survey']]);
  $ss=DB::table('survey_sessions')->first();
  $this->post('/s/'.$ss->resume_token,['action'=>'complete','q_'.$p['qid']=>'1']);
  $owner=$this->user();
  DB::table('qc_decisions')->insert(['survey_session_id'=>$ss->id,'decision'=>'valid','reason'=>'Test approved','decided_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
  $this->post('/s/'.$ss->resume_token.'/support',['type'=>'withdrawal','message'=>'Withdrawal test'])->assertRedirect();
  $this->assertDatabaseHas('participants',['id'=>$ss->participant_id,'state'=>'withdrawal_requested']);
  $manager=$this->user('data_manager');
  $this->actingAs($manager)->post('/admin/data/export',['scope'=>'clean','format'=>'csv'])->assertOk();
  $this->assertDatabaseHas('export_runs',['scope'=>'clean','rows'=>0]);
  $this->actingAs($manager)->post('/admin/data/export',['scope'=>'raw','format'=>'csv'])->assertOk();
  $this->assertDatabaseHas('export_runs',['scope'=>'raw','rows'=>1]);
  $request=DB::table('participant_requests')->first();
  $this->actingAs($owner)->post('/admin/requests/'.$request->id.'/resolve',['resolution'=>'Reviewed'])->assertSessionHasErrors('disposition');
  $this->actingAs($owner)->post('/admin/requests/'.$request->id.'/resolve',['resolution'=>'Protocol excludes record','disposition'=>'exclude'])->assertRedirect();
  $this->assertDatabaseHas('participants',['id'=>$ss->participant_id,'state'=>'withdrawn']);
 }
 public function test_codebook_csv_is_exportable_with_variable_mapping():void {
  $p=$this->publishedProject();
  $this->actingAs($this->user('data_manager'))->get('/admin/data/codebook.csv')
   ->assertOk()->assertDownload('research_codebook.csv')
   ->assertSee('Q'.$p['survey'].'_Q1',false);
 }


 public function test_cloned_draft_question_can_be_edited_but_published_question_cannot():void {
  $p=$this->publishedProject();$owner=$this->user();
  $this->actingAs($owner)->post('/admin/surveys/'.$p['survey'].'/questions/'.$p['qid'].'/edit',[
   'label'=>'Changed after publish','choices_text'=>"1=Yes\n2=No"
  ])->assertStatus(422);
  $this->actingAs($owner)->post('/admin/surveys/'.$p['survey'].'/clone')->assertRedirect();
  $draft=DB::table('surveys')->where('version',2)->first();
  $q=DB::table('questions')->where('survey_id',$draft->id)->first();
  $this->actingAs($owner)->post('/admin/surveys/'.$draft->id.'/questions/'.$q->id.'/edit',[
    'label'=>'Revised approved draft','choices_text'=>"1=Yes\n2=No\n3=Maybe",'required'=>'1'
  ])->assertRedirect();
  $this->assertDatabaseHas('questions',['id'=>$q->id,'label'=>'Revised approved draft']);
  $this->assertDatabaseHas('questions',['id'=>$p['qid'],'label'=>'Example approved question']);
 }


 public function test_draft_question_delete_respects_branches_and_published_lock():void {
  $p=$this->publishedProject();$owner=$this->user();
  $this->actingAs($owner)->post('/admin/surveys/'.$p['survey'].'/questions/'.$p['qid'].'/delete')->assertStatus(422);
  $this->actingAs($owner)->post('/admin/surveys/'.$p['survey'].'/clone')->assertRedirect();
  $draft=DB::table('surveys')->where('version',2)->first();
  $root=DB::table('questions')->where('survey_id',$draft->id)->first();
  $this->actingAs($owner)->post('/admin/surveys/'.$draft->id.'/questions',[
    'code'=>'Q2','label'=>'Branch','type'=>'text','condition_code'=>'Q1','condition_value'=>'1'
  ])->assertRedirect();
  $branch=DB::table('questions')->where('survey_id',$draft->id)->where('code','Q2')->first();
  $this->actingAs($owner)->post('/admin/surveys/'.$draft->id.'/questions/'.$root->id.'/delete')->assertSessionHasErrors('question');
  $this->actingAs($owner)->post('/admin/surveys/'.$draft->id.'/questions/'.$branch->id.'/delete')->assertRedirect();
  $this->actingAs($owner)->post('/admin/surveys/'.$draft->id.'/questions/'.$root->id.'/delete')->assertRedirect();
  $this->assertDatabaseCount('questions',1);
 }


 public function test_owner_can_revoke_viewer_access_but_cannot_lock_self():void {
  $owner=$this->user();$viewer=$this->user('viewer');
  $this->actingAs($owner)->post('/admin/users/'.$owner->id.'/toggle')->assertForbidden();
  $this->actingAs($owner)->post('/admin/users/'.$viewer->id.'/toggle')->assertRedirect();
  $this->assertDatabaseHas('users', [
    'id' => $viewer->id,
    'active' => false,
]);

// Nạp lại trạng thái tài khoản từ database.
$viewer->refresh();

// Tài khoản bị khóa phải bị từ chối truy cập.
$this->actingAs($viewer)
    ->get('/admin')
    ->assertForbidden();
  $this->actingAs($owner)->post('/admin/users/'.$viewer->id.'/toggle')->assertRedirect();
  $this->assertDatabaseHas('users',['id'=>$viewer->id,'active'=>true]);
 }

}
