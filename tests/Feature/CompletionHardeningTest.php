<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class CompletionHardeningTest extends TestCase {
 use RefreshDatabase;
 private function member(string $role): User
{
    $user = User::create([
        'name' => 'Demo '.$role,
        'email' => Str::random(9).'@example.test',
        'password' => 'example-password-123',
        'role' => $role,
    ]);

    $user->active = true;
    $user->save();

    return $user;
}
 private function start():array {
  config(['research.collection_open'=>true]);
  $site=DB::table('sites')->insertGetId(['code'=>'TEST_SITE','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $wave=DB::table('waves')->insertGetId(['code'=>'TEST_WAVE','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $info=DB::table('information_versions')->insertGetId(['version'=>1,'purpose'=>'Research','activities'=>'Survey','duration'=>'Ten minutes','voluntary'=>'Voluntary','withdrawal'=>'On request','contact'=>'Research office','data_use'=>'Research','status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  $survey=DB::table('surveys')->insertGetId(['group_code'=>'student','version'=>1,'title'=>'Test survey','status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  $q1=DB::table('questions')->insertGetId(['survey_id'=>$survey,'code'=>'Q1','label'=>'Choice one','type'=>'single','choices'=>'{"1":"Yes","2":"No"}','position'=>1,'required'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $q2=DB::table('questions')->insertGetId(['survey_id'=>$survey,'code'=>'Q2','label'=>'Choice two','type'=>'single','choices'=>'{"1":"One","2":"Two"}','position'=>2,'required'=>false,'created_at'=>now(),'updated_at'=>now()]);
  $token=Str::random(48);DB::table('sample_links')->insert(['site_id'=>$site,'wave_id'=>$wave,'group_code'=>'student','token'=>$token,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $this->post('/join/'.$token,['agree'=>'1','age_group'=>'adult','information_version_id'=>$info,'survey_id'=>$survey])->assertRedirect();
  $ss=DB::table('survey_sessions')->first();return compact('site','wave','info','survey','q1','q2','token','ss');
 }
 public function test_logic_conflict_rule_only_flags_approved_pair():void {
  $p=$this->start();$maker=$this->member('qc_reviewer');$owner=$this->member('owner');
  DB::table('quality_rules')->insert(['code'=>'logic_conflict','label'=>'Logic contradictions','threshold'=>0,'definition'=>'[{"left":"Q1","left_value":"1","right":"Q2","right_value":"2"}]','status'=>'approved','created_by'=>$maker->id,'approved_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
  $this->post('/s/'.$p['ss']->resume_token,['action'=>'complete','q_'.$p['q1']=>'1','q_'.$p['q2']=>'2'])->assertRedirect();
  $this->assertDatabaseCount('quality_flags',1);
  $this->assertStringContainsString('Q1/Q2',DB::table('quality_flags')->first()->evidence);
 }
 public function test_approved_recode_applies_to_clean_only_and_is_traced():void {
  $p=$this->start();$this->post('/s/'.$p['ss']->resume_token,['action'=>'complete','q_'.$p['q1']=>'1'])->assertRedirect();
  $owner=$this->member('owner');$analyst=$this->member('data_manager');
  DB::table('qc_decisions')->insert(['survey_session_id'=>$p['ss']->id,'decision'=>'valid','reason'=>'Test validation','decided_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($analyst)->post('/admin/data/cleaning-rules',['question_id'=>$p['q1'],'source_code'=>'1','target_code'=>'2','reason'=>'Approved synthetic test recode'])->assertRedirect();
  $rule=DB::table('cleaning_rules')->first();$this->assertSame('draft',$rule->status);
  $this->actingAs($analyst)->post('/admin/data/cleaning-rules/'.$rule->id.'/approve')->assertForbidden();
  $this->actingAs($owner)->post('/admin/data/cleaning-rules/'.$rule->id.'/approve')->assertRedirect();
  $clean=$this->actingAs($analyst)->post('/admin/data/export',['scope'=>'clean','format'=>'csv']);$clean->assertOk();
  $this->assertStringContainsString(',2,',str_replace("\r\n","\n",$clean->getContent()).',');
  $this->assertDatabaseHas('cleaning_applications',['survey_session_id'=>$p['ss']->id,'question_id'=>$p['q1'],'cleaning_rule_id'=>$rule->id,'before_value'=>'1','after_value'=>'2']);
  $this->assertDatabaseHas('answers',['survey_session_id'=>$p['ss']->id,'question_id'=>$p['q1'],'value'=>'"1"']);
  $this->actingAs($analyst)->post('/admin/data/export',['scope'=>'raw','format'=>'csv'])->assertOk();
  $this->assertSame(1,DB::table('cleaning_applications')->count());
 }
 public function test_duplicate_withdrawal_is_idempotent_and_prevents_new_submission():void {
  $p=$this->start();
  $url='/s/'.$p['ss']->resume_token.'/support';
  $this->post($url,['type'=>'withdrawal','message'=>'Withdraw request'])->assertRedirect();
  $this->post($url,['type'=>'withdrawal','message'=>'Duplicate request'])->assertRedirect();
  $this->assertDatabaseCount('participant_requests',1);
  $this->post('/s/'.$p['ss']->resume_token,['action'=>'complete','q_'.$p['q1']=>'1'])->assertForbidden();
  $this->assertDatabaseCount('answers',0);
 }
 public function test_sensitive_data_page_access_is_audited():void {
  $user=$this->member('data_manager');
  $this->actingAs($user)->get('/admin/data')->assertOk();
  $this->assertDatabaseHas('audit_events',['actor_id'=>$user->id,'action'=>'restricted.read']);
 }
 public function test_snapshot_command_creates_private_sha256_report():void {
  Storage::fake('local');$p=$this->start();$owner=$this->member('owner');
  DB::table('quota_configs')->insert(['site_id'=>$p['site'],'wave_id'=>$p['wave'],'group_code'=>'student','target'=>10,'version'=>1,'status'=>'approved','approved_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
  $this->artisan('research:snapshot')->assertExitCode(0);
  $row=DB::table('report_snapshots')->first();$this->assertNotNull($row);
  Storage::disk('local')->assertExists($row->storage_path);
  $this->assertSame(hash('sha256',Storage::disk('local')->get($row->storage_path)),$row->sha256);
 }
 public function test_small_cells_hidden_in_research_dashboard():void {
  $p=$this->start();$owner=$this->member('owner');
  $this->actingAs($owner)->get('/admin')->assertOk()->assertSee('[ẩn: ô nhỏ]');
 }
 public function test_invalid_logic_conflict_definition_is_rejected():void {
  $maker=$this->member('qc_reviewer');
  $this->actingAs($maker)->post('/admin/qc/rules',['code'=>'logic_conflict','threshold'=>0,'definition'=>'not JSON'])->assertSessionHasErrors('definition');
  $this->assertDatabaseCount('quality_rules',0);
 }
 public function test_transcript_needs_review_then_coding_ready_and_withdrawal_holds_export():void {
  config(['research.audio_approved'=>true]);$p=$this->start();$owner=$this->member('owner');
  $id=DB::table('transcripts')->insertGetId(['participant_id'=>$p['ss']->participant_id,'body'=>'Synthetic transcript only','status'=>'transcribed','uploaded_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($owner)->post('/admin/data/transcripts/'.$id.'/review',['status'=>'coding_ready','reason'=>'Stage must be reviewed first'])->assertStatus(422);
  $this->actingAs($owner)->post('/admin/data/transcripts/'.$id.'/review',['status'=>'reviewed','reason'=>'Verified synthetic transcript'])->assertRedirect();
  $this->actingAs($owner)->post('/admin/data/transcripts/'.$id.'/review',['status'=>'coding_ready','reason'=>'Ready for coding'])->assertRedirect();
  $this->assertDatabaseCount('transcript_review_events',2);
  $this->actingAs($owner)->get('/admin/data/transcripts/export')->assertOk()->assertSee('Synthetic transcript only');
  DB::table('participants')->where('id',$p['ss']->participant_id)->update(['state'=>'withdrawal_requested']);
  $this->actingAs($owner)->get('/admin/data/transcripts/export')->assertOk()->assertDontSee('Synthetic transcript only');
 }

}
