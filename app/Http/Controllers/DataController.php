<?php
namespace App\Http\Controllers;
use App\Services\SimpleXlsx;
use App\Services\SpssSyntax;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class DataController extends Controller {
 public function index() {
   return view('data.index',['runs'=>DB::table('export_runs')->orderByDesc('id')->limit(20)->get(),'logs'=>DB::table('cleaning_logs')->orderByDesc('id')->limit(40)->get(),'transcripts'=>DB::table('transcripts as t')->join('participants as p','t.participant_id','=','p.id')->select('t.*','p.research_id')->orderByDesc('t.id')->limit(40)->get(),'count'=>DB::table('survey_sessions')->where('status','complete')->count()]);
 }
 public function codebook() {
   $questions=DB::table('questions as q')->join('surveys as s','q.survey_id','=','s.id')->select('q.*','s.group_code','s.version')->orderBy('s.id')->orderBy('q.position')->get();return view('data.codebook',compact('questions'));
 }
 public function codebookCsv() {
   $qs=DB::table('questions as q')->join('surveys as s','q.survey_id','=','s.id')->select('q.*','s.group_code','s.version')->orderBy('s.id')->orderBy('q.position')->get();
   $fp=fopen('php://temp','w+');fwrite($fp,"\xEF\xBB\xBF");
   fputcsv($fp,['VARIABLE','GROUP','SURVEY_VERSION','QUESTION_CODE','QUESTION_LABEL','TYPE','REQUIRED','CHOICES_JSON','CONDITION_JSON','MISSING_CODE','SCALE_SOURCE','MIN_VALUE','MAX_VALUE'],',','"','');
   foreach($qs as $q){$cells=['Q'.$q->survey_id.'_'.strtoupper($q->code),$q->group_code,$q->version,$q->code,$q->variable_label??$q->label,$q->type,(int)$q->required,$q->choices,$q->condition,$q->missing_code,$q->scale,$q->min_value,$q->max_value];$cells=array_map(fn($v)=>is_string($v)&&!is_numeric($v)&&preg_match('/^[\s]*[=+\-@]/u',$v)?"'".$v:$v,$cells);fputcsv($fp,$cells,',','"','');}
   rewind($fp);$csv=stream_get_contents($fp);fclose($fp);
   Audit::log('data.codebook_export','questions',null,['rows'=>$qs->count()]);
   return response($csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="research_codebook.csv"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function validation() {
  $raw=DB::table('survey_sessions')->where('status','complete')->count();$distinct=DB::table('survey_sessions as ss')->join('participants as p','ss.participant_id','=','p.id')->where('ss.status','complete')->distinct()->count('p.research_id');$valid=DB::table('survey_sessions as ss')->where('ss.status','complete')->whereRaw("(SELECT d.decision FROM qc_decisions d WHERE d.survey_session_id=ss.id ORDER BY d.id DESC LIMIT 1)='valid'")->count();
  $clean=DB::table('survey_sessions as ss')->join('participants as p','ss.participant_id','=','p.id')->where('ss.status','complete')
   ->whereRaw("(SELECT d.decision FROM qc_decisions d WHERE d.survey_session_id=ss.id ORDER BY d.id DESC LIMIT 1)='valid'")
   ->whereNotIn('p.state',['withdrawal_requested','withdrawn'])
   ->whereNotExists(function($q){$q->selectRaw('1')->from('participant_requests as r')->whereColumn('r.participant_id','p.id')->where('r.type','withdrawal')->where('r.status','!=','resolved');})
   ->count();
  $missing=DB::table('answers')->where(function($q){$q->whereNull('value')->orWhere('value','null');})->count();$invalid=DB::table('answers')->whereRaw('JSON_VALID(value) = 0')->count();return view('data.validation',compact('raw','distinct','valid','clean','missing','invalid'));
 }
 private function build(string $scope):array {
  $qs=DB::table('questions')->orderBy('survey_id')->orderBy('position')->get();$headers=['RESEARCH_ID','SITE_CODE','WAVE_CODE','GROUP_CODE','SURVEY_VERSION','INFO_VERSION','QC_STATUS','COMPLETED_AT'];$codes=[];
  foreach($qs as $q){$col='Q'.$q->survey_id.'_'.strtoupper($q->code);$headers[]=$col;$codes[$q->id]=$col;}
  $query=DB::table('survey_sessions as ss')->join('participants as p','ss.participant_id','=','p.id')->join('sites as site','p.site_id','=','site.id')->join('waves as wave','p.wave_id','=','wave.id')->join('surveys as survey','ss.survey_id','=','survey.id')->leftJoin('consents as c', function ($join) {
    $join->on('c.id', '=', DB::raw(
        '(SELECT c2.id FROM consents c2
          WHERE c2.participant_id = p.id
            AND c2.decision = \'agreed\'
            AND c2.recorded_at <= ss.started_at
          ORDER BY c2.recorded_at DESC, c2.id DESC
          LIMIT 1)'
    ));
})->select('ss.id','ss.completed_at','p.research_id','p.id as participant_id','p.group_code','site.code as site','wave.code as wave','survey.version as survey_version','c.information_version_id as info_version','p.state as participant_state')->where('ss.status','complete')->orderBy('ss.id');
  $rows=[];$applied=[];
  $rules= $scope==='clean' ? DB::table('cleaning_rules')->where('status','approved')->orderBy('id')->get()->groupBy('question_id') : collect();
  foreach($query->get() as $s){$decision=DB::table('qc_decisions')->where('survey_session_id',$s->id)->orderByDesc('id')->value('decision')??'unreviewed';if($scope==='clean'&&($decision!=='valid'||in_array($s->participant_state,['withdrawal_requested','withdrawn'],true)||DB::table('participant_requests')->where('participant_id',$s->participant_id)->where('type','withdrawal')->where('status','!=','resolved')->exists()))continue;$a=DB::table('answers')->where('survey_session_id',$s->id)->pluck('value','question_id');$r=['RESEARCH_ID'=>$s->research_id,'SITE_CODE'=>$s->site,'WAVE_CODE'=>$s->wave,'GROUP_CODE'=>$s->group_code,'SURVEY_VERSION'=>$s->survey_version,'INFO_VERSION'=>$s->info_version,'QC_STATUS'=>$decision,'COMPLETED_AT'=>$s->completed_at];foreach($codes as $qid=>$col){
    $val=$a->has($qid)?json_decode($a[$qid],true):null;
    if($scope==='clean' && is_scalar($val))foreach($rules->get($qid,collect()) as $rule){
      if((string)$val===$rule->source_code){
        $before=(string)$val;$val=$rule->target_code;
        $applied[]=['survey_session_id'=>$s->id,'question_id'=>$qid,'cleaning_rule_id'=>$rule->id,'before_value'=>$before,'after_value'=>$val];break;
      }
    }
    $r[$col]=is_scalar($val)?(string)$val:(is_null($val)?'':json_encode($val,JSON_UNESCAPED_UNICODE));
   }$rows[]=array_map(fn($key)=>(string)($r[$key]??''),$headers);}
  return [$headers,$rows,$applied];
 }
 public function export(Request $r) {
   $d=$r->validate(['scope'=>'required|in:raw,clean','format'=>'required|in:csv,xlsx,sps']);[$headers,$rows,$applied]=$this->build($d['scope']);$base='research_'.$d['scope'].'_'.now()->format('Ymd_His');
   if($d['format']==='sps'){$bytes=SpssSyntax::build($headers,DB::table('questions')->get());$mime='text/plain; charset=UTF-8';$ext='sps';}
   elseif($d['format']==='csv'){$handle=fopen('php://temp','w+');fwrite($handle,"\xEF\xBB\xBF");fputcsv($handle,$headers,',','"','');foreach($rows as $row){$safe=array_map(fn($v)=>(!is_numeric($v)&&preg_match('/^[\s]*[=+\-@]/u',$v))?"'".$v:$v,$row);fputcsv($handle,$safe,',','"','');}rewind($handle);$bytes=stream_get_contents($handle);fclose($handle);$mime='text/csv; charset=UTF-8';$ext='csv';}
   else {$tmp=tempnam(sys_get_temp_dir(),'rfx');SimpleXlsx::write($headers,$rows,$tmp);$bytes=file_get_contents($tmp);unlink($tmp);$mime='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';$ext='xlsx';}
   $id=DB::table('export_runs')->insertGetId(['format'=>$ext,'scope'=>$d['scope'],'rows'=>count($rows),'sha256'=>hash('sha256',$bytes),'actor_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);if($d['format']!=='sps' && $applied){foreach(array_chunk($applied,250) as $chunk){foreach($chunk as &$item){$item['export_run_id']=$id;$item['created_at']=now();}unset($item);DB::table('cleaning_applications')->insert($chunk);}}
   Audit::log('data.export','export_runs',$id,['rows'=>count($rows),'format'=>$ext,'scope'=>$d['scope'],'recode_applications'=>$d['format']==='sps'?0:count($applied)]);return response($bytes,200,['Content-Type'=>$mime,'Content-Disposition'=>'attachment; filename="'.$base.'.'.$ext.'"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
 }
 public function logCleaning(Request $r){$d=$r->validate(['survey_session_id'=>'required|exists:survey_sessions,id','operation'=>'required|in:recode,missing,exclusion,derived,annotation','reason'=>'required|string|max:3000','before_value'=>'nullable|string|max:2000','after_value'=>'nullable|string|max:2000']);$id=DB::table('cleaning_logs')->insertGetId($d+['actor_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);Audit::log('data.cleaning_annotation','cleaning_logs',$id);return back()->with('ok','Đã ghi log. Đây là nhật ký, không sửa dữ liệu gốc.');}
 public function transcript(Request $r){$d=$r->validate(['research_id'=>'required|uuid|exists:participants,research_id','audio'=>'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/ogg|max:20480','body'=>'nullable|string|max:200000','permission_confirmed'=>'required|accepted']);abort_unless(config('research.audio_approved'),403,'Chưa kích hoạt quy trình âm thanh được duyệt.');$p=DB::table('participants')->where('research_id',$d['research_id'])->first();abort_unless(DB::table('consents')->where('participant_id',$p->id)->where('decision','agreed')->exists(),422);$path=null;$ext=null;if($r->hasFile('audio')){$ext=$r->file('audio')->extension();$path=$r->file('audio')->store('private/audio');}abort_unless($path||!empty($d['body']),422,'Cần file hoặc transcript.');$id=DB::table('transcripts')->insertGetId(['participant_id'=>$p->id,'audio_path'=>$path,'original_extension'=>$ext,'body'=>$d['body']??null,'status'=>isset($d['body'])?'transcribed':'received','uploaded_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);Audit::log('transcript.registered','transcripts',$id);return back()->with('ok','Đã đăng ký file/transcript bảo mật, dùng Research ID.');}
 public function transcribe(Request $r,int $id){
  abort_unless(config('research.audio_approved'),403,'Luồng transcript chưa được phê duyệt.');
  $d=$r->validate(['body'=>'required|string|max:200000']);
  DB::transaction(function()use($id,$d){
   $record=DB::table('transcripts')->where('id',$id)->lockForUpdate()->first();abort_unless($record&&$record->status==='received',422,'Chỉ được thêm bản chép lời cho mục âm thanh chưa xử lý.');
   $p=DB::table('participants')->find($record->participant_id);abort_if(in_array($p->state,['withdrawal_requested','withdrawn'],true),422,'Dữ liệu đang bị giữ theo yêu cầu rút lui.');
   DB::table('transcripts')->where('id',$id)->update(['body'=>$d['body'],'status'=>'transcribed','updated_at'=>now()]);
   Audit::log('transcript.transcribed','transcripts',$id,['sha256'=>hash('sha256',$d['body'])]);
  });return back()->with('ok','Đã thêm nội dung transcript, tiếp theo cần người quản lý duyệt.');
 }
 public function reviewTranscript(Request $r,int $id){
  abort_unless(config('research.audio_approved'),403,'Luồng transcript chưa được phê duyệt.');
  $d=$r->validate(['status'=>'required|in:reviewed,coding_ready,rejected','reason'=>'required|string|max:3000']);
  DB::transaction(function()use($id,$d){
   $t=DB::table('transcripts')->where('id',$id)->lockForUpdate()->first();abort_unless($t,404);
   $allowed=match($d['status']){
    'reviewed'=>['transcribed'],
    'coding_ready'=>['reviewed'],
    'rejected'=>['received','transcribed','reviewed'],
   };
   abort_unless(in_array($t->status,$allowed,true),422,'Không hợp lệ: transcript phải có nội dung đã nhập, được review trước khi coding-ready.');
   // Protect researcher withdrawals until their disposition is authorized.
   $p=DB::table('participants')->find($t->participant_id);
   abort_if(in_array($p->state,['withdrawal_requested','withdrawn'],true),422,'Transcript đang bị giữ theo yêu cầu rút lui.');
   DB::table('transcripts')->where('id',$id)->update(['status'=>$d['status'],'updated_at'=>now()]);
   DB::table('transcript_review_events')->insert(['transcript_id'=>$id,'status'=>$d['status'],'reason'=>$d['reason'],'reviewer_id'=>auth()->id(),'created_at'=>now()]);
   Audit::log('transcript.review','transcripts',$id,['status'=>$d['status']]);
  });return back()->with('ok','Đã ghi trạng thái transcript và lịch sử kiểm tra.');
 }
 public function transcriptExport(){
  abort_unless(config('research.audio_approved'),403,'Luồng transcript chưa được phê duyệt.');
  $rows=DB::table('transcripts as t')->join('participants as p','t.participant_id','=','p.id')
   ->select('p.research_id','t.id','t.body','t.updated_at')
   ->where('t.status','coding_ready')->whereNotIn('p.state',['withdrawal_requested','withdrawn'])
   ->whereNotExists(function($q){$q->selectRaw('1')->from('participant_requests as r')->whereColumn('r.participant_id','p.id')->where('r.type','withdrawal')->where('r.status','!=','resolved');})
   ->orderBy('t.id')->get();
  $fp=fopen('php://temp','w+');fwrite($fp,"\xEF\xBB\xBF");
  fputcsv($fp,['RESEARCH_ID','TRANSCRIPT_ID','BODY','REVIEWED_AT'],',','"','');
  foreach($rows as $t){
   $body=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)$t->body);
   $body=preg_match('/^\s*[=+@\-]/u',$body)?"'".$body:$body;
   fputcsv($fp,[$t->research_id,$t->id,$body,$t->updated_at],',','"','');
  }
  rewind($fp);$csv=stream_get_contents($fp);fclose($fp);
  Audit::log('transcript.export','transcripts',null,['rows'=>$rows->count(),'sha256'=>hash('sha256',$csv)]);
  return response($csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="coding_ready_transcripts.csv"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }

}
