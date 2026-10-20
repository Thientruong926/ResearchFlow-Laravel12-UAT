<?php
namespace App\Http\Controllers;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class QcController extends Controller {
 private const RULES=['too_fast'=>'Hoàn thành quá nhanh (giây)','missing_pct'=>'Tỷ lệ thiếu (%)','straightlining_pct'=>'Tỷ lệ lặp cùng lựa chọn (%)','duplicate_token'=>'Thử gửi lại cùng token (ngưỡng để 0)','logic_conflict'=>'Mâu thuẫn giữa hai mã câu trả lời (JSON đã duyệt)'];
 public function index(){
  $queue=DB::table('survey_sessions as ss')->join('participants as p','p.id','=','ss.participant_id')->leftJoin('qc_decisions as d',function($j){$j->on('d.survey_session_id','=','ss.id')->whereRaw('d.id=(SELECT MAX(d2.id) FROM qc_decisions d2 WHERE d2.survey_session_id=ss.id)');})->select('ss.id','ss.completed_at','p.research_id','p.group_code','d.decision',DB::raw('(SELECT COUNT(*) FROM quality_flags f WHERE f.survey_session_id=ss.id) as flags'))->where('ss.status','complete')->orderByDesc('ss.id')->limit(300)->get();
  return view('qc.index',['rules'=>DB::table('quality_rules')->orderBy('code')->get(),'queue'=>$queue,'ruleTypes'=>self::RULES]);
 }
 public function addRule(Request $r){$d=$r->validate(['code'=>['required',Rule::in(array_keys(self::RULES)),'unique:quality_rules,code'],'threshold'=>'required|numeric|min:0|max:100000','definition'=>'required|string|max:3000']);if($d['code']==='logic_conflict'){
    $pairs=json_decode($d['definition'],true);
    if(!is_array($pairs)||!array_is_list($pairs)||!count($pairs)||count($pairs)>30)return back()->withErrors(['definition'=>'Cần một JSON array có từ 1 đến 30 cặp mâu thuẫn.']);
    foreach($pairs as $pair){if(!is_array($pair)||count($pair)!==4||array_diff(['left','left_value','right','right_value'],array_keys($pair))||!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,30}$/D',(string)$pair['left'])||!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,30}$/D',(string)$pair['right'])||$pair['left']===$pair['right']||!is_scalar($pair['left_value'])||!is_scalar($pair['right_value']))return back()->withErrors(['definition'=>'Mỗi mâu thuẫn cần left, left_value, right, right_value với mã câu hỏi hợp lệ.']);}
    $d['definition']=json_encode($pairs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $d['threshold']=0;
   }
   if(in_array($d['code'],['missing_pct','straightlining_pct'])&&$d['threshold']>100)return back()->withErrors(['threshold'=>'Giới hạn tỷ lệ từ 0 đến 100%.']);$d['label']=self::RULES[$d['code']];$id=DB::table('quality_rules')->insertGetId($d+['status'=>'draft','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);Audit::log('qc.rule_drafted','quality_rules',$id);return back()->with('ok','Đã tạo quy tắc QC chờ duyệt.');}
 public function approveRule(int $id){$rule=DB::table('quality_rules')->find($id);abort_unless($rule&&$rule->status==='draft',422);abort_if($rule->created_by===auth()->id(),403,'Không được tự duyệt quy tắc QC của chính mình.');DB::table('quality_rules')->where('id',$id)->update(['status'=>'approved','approved_by'=>auth()->id(),'updated_at'=>now()]);Audit::log('qc.rule_approved','quality_rules',$id);return back()->with('ok','Đã duyệt cờ QC. Cờ sẽ được áp dụng cho lượt nộp mới.');}
 public function decide(Request $r,int $id){$session=DB::table('survey_sessions')->find($id);abort_unless($session&&$session->status==='complete',422);$d=$r->validate(['decision'=>'required|in:valid,review,exclude','reason'=>'required|string|max:3000']);$did=DB::table('qc_decisions')->insertGetId(['survey_session_id'=>$id,'decision'=>$d['decision'],'reason'=>$d['reason'],'decided_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);Audit::log('qc.decision','qc_decisions',$did,['decision'=>$d['decision']]);return back()->with('ok','Đã ghi nhận quyết định QC và lý do.');}
 public function flags(int $id){$session=DB::table('survey_sessions')->find($id);abort_unless($session,404);return view('qc.flags',['flags'=>DB::table('quality_flags as f')->join('quality_rules as r','f.quality_rule_id','=','r.id')->where('f.survey_session_id',$id)->select('f.*','r.code','r.definition')->get(),'decisions'=>DB::table('qc_decisions as d')->join('users as u','d.decided_by','=','u.id')->where('survey_session_id',$id)->select('d.*','u.email')->orderByDesc('d.id')->get(),'session'=>$session]);}
}
