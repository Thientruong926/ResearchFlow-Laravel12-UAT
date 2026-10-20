<?php
namespace App\Http\Controllers;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CleaningRuleController extends Controller {
 public function index(){
  return view('data.cleaning_rules',['questions'=>DB::table('questions as q')->join('surveys as s','q.survey_id','=','s.id')->whereIn('q.type',['single','likert'])->select('q.*','s.group_code','s.version')->orderBy('s.group_code')->orderBy('q.position')->get(),
   'rules'=>DB::table('cleaning_rules as r')->join('questions as q','r.question_id','=','q.id')->join('surveys as s','q.survey_id','=','s.id')->select('r.*','q.code','s.group_code','s.version')->orderByDesc('r.id')->limit(300)->get()]);
 }
 public function create(Request $r){
  $d=$r->validate(['question_id'=>'required|integer|exists:questions,id','source_code'=>'required|string|max:64','target_code'=>'nullable|string|max:64','reason'=>'required|string|max:3000']);
  $q=DB::table('questions')->find($d['question_id']);abort_unless(in_array($q->type,['single','likert'],true),422,'Chỉ recode biến lựa chọn.');
  $choices=json_decode($q->choices??'{}',true)?:[];
  if(!array_key_exists($d['source_code'],$choices))return back()->withErrors(['source_code'=>'Mã nguồn phải nằm trong các lựa chọn của câu hỏi.']);
  $to=$d['target_code']??null;
  if($to!==null && !array_key_exists($to,$choices) && $to!==$q->missing_code)return back()->withErrors(['target_code'=>'Mã đích phải thuộc lựa chọn hoặc là missing_code đã định nghĩa.']);
  if($to===$d['source_code'])return back()->withErrors(['target_code'=>'Mã nguồn và đích không được giống nhau.']);
  if(DB::table('cleaning_rules')->where('question_id',$q->id)->where('source_code',$d['source_code'])->whereIn('status',['draft','approved'])->exists())return back()->withErrors(['source_code'=>'Đã có quy tắc nháp/được duyệt cho mã này.']);
  $id=DB::table('cleaning_rules')->insertGetId($d+['created_by'=>auth()->id(),'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
  Audit::log('cleaning.rule_drafted','cleaning_rules',$id);return back()->with('ok','Đã tạo quy tắc nháp, chưa áp dụng vào dữ liệu.');
 }
 public function approve(int $id){
  DB::transaction(function()use($id){
   $rule=DB::table('cleaning_rules')->where('id',$id)->lockForUpdate()->first();abort_unless($rule&&$rule->status==='draft',422);
   abort_if((int)$rule->created_by===auth()->id(),403,'Người tạo không được tự duyệt quy tắc.');
   abort_if(DB::table('cleaning_rules')->where('question_id',$rule->question_id)->where('source_code',$rule->source_code)->where('status','approved')->exists(),422,'Đã có quy tắc được duyệt cho mã này.');
   DB::table('cleaning_rules')->where('id',$id)->update(['status'=>'approved','approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);
   Audit::log('cleaning.rule_approved','cleaning_rules',$id);
  });return back()->with('ok','Quy tắc đã duyệt. Chỉ áp dụng cho các bản xuất clean mới.');
 }
 public function retire(int $id){
  DB::transaction(function()use($id){
   $rule=DB::table('cleaning_rules')->where('id',$id)->lockForUpdate()->first();abort_unless($rule&&in_array($rule->status,['draft','approved'],true),422);
   DB::table('cleaning_rules')->where('id',$id)->update(['status'=>'retired','updated_at'=>now()]);
   Audit::log('cleaning.rule_retired','cleaning_rules',$id);
  });return back()->with('ok','Quy tắc đã ngừng hiệu lực; các bản xuất cũ vẫn truy vết được.');
 }
}
