<?php
namespace App\Http\Controllers;
use App\Services\QualityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class PortalController extends Controller {
 public function home() {return view('portal.home',['published'=>DB::table('information_versions')->where('status','published')->exists()]);}
 private function link(string $token){$link=DB::table('sample_links')->where('token',$token)->where('active',true)->first();abort_unless($link && config('research.collection_open'),404,'Liên kết chưa mở.');
  abort_unless(DB::table('sites')->where('id',$link->site_id)->where('active',true)->exists() && DB::table('waves')->where('id',$link->wave_id)->where('active',true)->exists(),404,'Điểm hoặc đợt khảo sát đã ngừng.');return $link;}
 public function intro(string $token){$link=$this->link($token);$info=DB::table('information_versions')->where('status','published')->orderByDesc('version')->first();$survey=DB::table('surveys')->where('group_code',$link->group_code)->where('status','published')->orderByDesc('version')->first();abort_unless($info&&$survey,503,'Chưa có thông tin nghiên cứu và bảng hỏi được duyệt.');return view('portal.intro',compact('link','info','survey'));}
 public function join(Request $r,string $token) {
  $link=$this->link($token);$d=$r->validate(['agree'=>'required|accepted','age_group'=>'required|in:adult,minor','guardian_permission'=>'sometimes|accepted','assent'=>'sometimes|accepted','information_version_id'=>'required|integer','survey_id'=>'required|integer']);
  if($d['age_group']==='minor'&&!config('research.minor_flow'))return back()->withErrors(['age_group'=>'Nghiên cứu chưa kích hoạt quy trình được duyệt dành cho người dưới 18 tuổi.']);
  if($d['age_group']==='minor'&&(!$r->boolean('guardian_permission')||!$r->boolean('assent')))return back()->withErrors(['assent'=>'Cần permission của người giám hộ và assent theo quy trình đã được duyệt.']);
  $info=DB::table('information_versions')->where('status','published')->orderByDesc('version')->first();$survey=DB::table('surveys')->where('group_code',$link->group_code)->where('status','published')->orderByDesc('version')->first();abort_unless($info&&$survey,503);if((int)$d['information_version_id']!==$info->id||(int)$d['survey_id']!==$survey->id)return back()->withErrors(['agree'=>'Phiên bản thông tin hoặc bảng hỏi đã thay đổi; hãy tải lại trang, đọc nội dung mới và xác nhận lại.']);
  $resume=Str::random(50);
  DB::transaction(function()use($link,$d,$info,$survey,$resume,$r){$pid=DB::table('participants')->insertGetId(['research_id'=>(string)Str::uuid(),'site_id'=>$link->site_id,'wave_id'=>$link->wave_id,'group_code'=>$link->group_code,'minor'=>$d['age_group']==='minor','state'=>'consented','created_at'=>now(),'updated_at'=>now()]);DB::table('consents')->insert(['participant_id'=>$pid,'information_version_id'=>$info->id,'decision'=>'agreed','guardian_permission'=>$r->boolean('guardian_permission'),'assent'=>$r->boolean('assent'),'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);DB::table('survey_sessions')->insert(['participant_id'=>$pid,'survey_id'=>$survey->id,'resume_token'=>$resume,'started_at'=>now(),'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);});
  return redirect()->route('survey.fill',$resume);
 }
 private function session(string $token) {$session=DB::table('survey_sessions')->where('resume_token',$token)->first();abort_unless($session,404);return $session;}
 public function survey(string $resume) {
  $session=$this->session($resume);$survey=DB::table('surveys')->find($session->survey_id);$qs=DB::table('questions')->where('survey_id',$survey->id)->orderBy('position')->get();$answers=DB::table('answers')->where('survey_session_id',$session->id)->get()->mapWithKeys(fn($a)=>[$a->question_id=>json_decode($a->value,true)])->all();$person=DB::table('participants')->find($session->participant_id);
  return view('survey.fill',compact('session','survey','qs','answers','person'));
 }
 public function save(Request $r,string $resume,QualityService $quality) {
  abort_unless(config('research.collection_open'), 403, 'Đã tạm dừng thu thập dữ liệu; không thể lưu hoặc nộp.');
  $session=$this->session($resume);$participant=DB::table('participants')->find($session->participant_id);abort_if(in_array($participant->state,['withdrawal_requested','withdrawn'],true),403,'Yêu cầu rút lui đang được xử lý; không thể tiếp tục nộp khảo sát.');if($session->status==='complete'){$quality->reusedToken($session->id);return redirect()->route('survey.fill',$resume)->with('ok','Bảng hỏi đã được nộp; không ghi thêm câu trả lời.');}
  $type=$r->input('action')==='complete'?'complete':'draft';$qs=DB::table('questions')->where('survey_id',$session->survey_id)->orderBy('position')->get();$values=[];$byCode=[];$error=[];
  foreach($qs as $q) {
    $key='q_'.$q->id;$value=$r->input($key);$cond=json_decode($q->condition??'null',true);$visible=!$cond||($byCode[$cond['code']]??null)===$cond['equals'];
    if(!$visible){$byCode[$q->code]=null;continue;}
    if($value!==null&&$value!==''){
      if($q->type==='number') {if(!is_numeric($value)||strlen((string)$value)>50){$error[$key]='Cần nhập số hợp lệ.';}else {if(($q->min_value!==null&&(float)$value<(float)$q->min_value)||($q->max_value!==null&&(float)$value>(float)$q->max_value))$error[$key]='Số nằm ngoài khoảng cho phép.';$value=(string)$value;}}
      elseif(in_array($q->type,['single','likert'],true)){$allowed=json_decode($q->choices??'{}',true)?:[];if(!array_key_exists((string)$value,$allowed))$error[$key]='Lựa chọn không hợp lệ.';}
      elseif(!is_string($value)||mb_strlen($value)>5000)$error[$key]='Văn bản vượt 5000 ký tự.';
    }else $value=null;
    if($type==='complete'&&$q->required&&$value===null)$error[$key]='Câu hỏi bắt buộc chưa được trả lời.';
    $values[$q->id]=$value;$byCode[$q->code]=$value;
  }
  if($error)throw ValidationException::withMessages($error);
  DB::transaction(function()use($values,$session,$type){$locked=DB::table('survey_sessions')->where('id',$session->id)->lockForUpdate()->first();if(!$locked || $locked->status==='complete')return;foreach($values as $qid=>$value){DB::table('answers')->updateOrInsert(['survey_session_id'=>$session->id,'question_id'=>$qid],['value'=>json_encode($value,JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);} // Clear hidden/skipped answers to enforce branch consistency.
    $keep=array_keys($values);$q=DB::table('answers')->where('survey_session_id',$session->id);if($keep)$q->whereNotIn('question_id',$keep);$q->delete();
    DB::table('survey_sessions')->where('id',$session->id)->update(['status'=>$type,'completed_at'=>$type==='complete'?now():null,'updated_at'=>now()]);});
  if($type==='complete') {$quality->evaluate($session->id);return redirect()->route('survey.fill',$resume)->with('ok','Đã hoàn tất khảo sát. Vui lòng lưu mã Research ID để gửi yêu cầu hỗ trợ nếu cần.');}
  return back()->with('ok','Đã lưu nháp thành công. Dùng lại đường dẫn hiện tại để tiếp tục.');
 }
 public function support(string $resume) {$session=$this->session($resume);$person=DB::table('participants')->find($session->participant_id);return view('portal.support',compact('resume','person'));}
 public function request(Request $r,string $resume) {$session=$this->session($resume);$d=$r->validate(['type'=>'required|in:support,withdrawal','message'=>'required|string|max:3000']);$id=DB::transaction(function()use($session,$d){
   $participant=DB::table('participants')->where('id',$session->participant_id)->lockForUpdate()->first();
   if($d['type']==='withdrawal'){
     abort_if($participant->state==='withdrawn',422,'Yêu cầu rút lui đã được xử lý.');
     $existing=DB::table('participant_requests')->where('participant_id',$session->participant_id)->where('type','withdrawal')->where('status','!=','resolved')->first();
     if($existing)return $existing->id;
     DB::table('participants')->where('id',$session->participant_id)->update(['state'=>'withdrawal_requested','updated_at'=>now()]);
   }
   return DB::table('participant_requests')->insertGetId(['participant_id'=>$session->participant_id,'type'=>$d['type'],'message'=>$d['message'],'status'=>'new','created_at'=>now(),'updated_at'=>now()]);
  });return redirect()->route('portal.support',$resume)->with('ok','Yêu cầu #'.$id.' đã được tiếp nhận, nghiên cứu sinh sẽ xử lý theo quy trình được duyệt.');}
}
