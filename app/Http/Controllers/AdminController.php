<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class AdminController extends Controller {
 private const GROUPS=['manager','lecturer','student','BGH','KHOA','GV','SV'];
 public function dashboard() {
   if(auth()->user()->hasRole('SITE_COORDINATOR','AUDITOR','ANALYST','IT_ADMIN','NCS')) {
     return redirect()->route(match(auth()->user()->role) {
       'SITE_COORDINATOR'=>'admin.site.status', 'AUDITOR'=>'admin.research.audit',
       'ANALYST'=>'admin.analysis', default=>'admin.sites',
     });
   }
   $counts=['sites'=>DB::table('sites')->count(),'links'=>DB::table('sample_links')->count(),'participants'=>DB::table('participants')->count(),'complete'=>DB::table('survey_sessions')->where('status','complete')->count(),'drafts'=>DB::table('survey_sessions')->where('status','draft')->count(),'flags'=>DB::table('quality_flags')->count(),'requests'=>DB::table('participant_requests')->where('status','!=','resolved')->count()];
   $rows=DB::table('survey_sessions as ss')->join('participants as p','ss.participant_id','=','p.id')->join('sites as si','p.site_id','=','si.id')->join('waves as w','p.wave_id','=','w.id')->selectRaw('si.code as site_code,w.code as wave_code,p.group_code,COUNT(*) as total,SUM(CASE WHEN ss.status = ? THEN 1 ELSE 0 END) as complete',['complete'])->groupBy('si.code','w.code','p.group_code')->orderBy('si.code')->get();
   $alerts=DB::table('quota_configs as q')->join('sites as s','q.site_id','=','s.id')->join('waves as w','q.wave_id','=','w.id')->where('q.status','approved')->select('q.*','s.code as site_code','w.code as wave_code')->get()->map(function($q){$q->completed=DB::table('survey_sessions as ss')->join('participants as p','ss.participant_id','=','p.id')->where('p.site_id',$q->site_id)->where('p.wave_id',$q->wave_id)->where('p.group_code',$q->group_code)->where('ss.status','complete')->count();return $q;});
   // Viewer role gets high-level counts only; fine-grained site/group breakdown requires a research role.
   if(!auth()->user()->hasRole('owner','researcher','quota_manager')){$rows=collect();$alerts=collect();}
   $min=config('research.dashboard_min_cell',5);
   $rows=$rows->map(function($row)use($min){$row->suppressed=$row->total>0 && $row->total<$min;return $row;});
   $alerts=$alerts->map(function($row)use($min){$row->suppressed=$row->completed>0 && $row->completed<$min;return $row;});
   return view('admin.dashboard',compact('counts','rows','alerts'));
 }
 public function sites() {return view('admin.sites',['sites'=>DB::table('sites')->orderBy('code')->get(),'waves'=>DB::table('waves')->orderBy('code')->get(),'departments'=>DB::table('departments')->orderBy('code')->get(),'surveys'=>DB::table('surveys')->where('status','published')->get(),'owners'=>User::where('active',true)->whereIn('role',['owner','researcher','IT_ADMIN','NCS'])->get(),'links'=>DB::table('sample_links as l')->join('sites as s','l.site_id','=','s.id')->join('waves as w','l.wave_id','=','w.id')->select('l.*','s.code as site','w.code as wave')->orderByDesc('l.id')->get()]);}
 public function addSite(Request $r) {$d=$r->validate(['code'=>['nullable','regex:/^[A-Z0-9_-]{2,32}$/D','unique:sites,code'],'private_label'=>'nullable|string|max:255']);$d['code']=$d['code']??app(\App\Services\ResearchId::class)->registryCode('S');$id=DB::table('sites')->insertGetId($d+['created_at'=>now(),'updated_at'=>now()]);Audit::log('site.created','sites',$id);return back()->with('ok','Đã thêm mã điểm mẫu.');}
 public function quota() {return view('admin.quota',['quotas'=>DB::table('quota_configs as q')->join('sites as s','q.site_id','=','s.id')->join('waves as w','q.wave_id','=','w.id')->select('q.*','s.code as site','w.code as wave')->orderByDesc('q.id')->get(),'sites'=>DB::table('sites')->get(),'waves'=>DB::table('waves')->get()]);}
 public function addQuota(Request $r) {$d=$r->validate(['site_id'=>'required|exists:sites,id','wave_id'=>'required|exists:waves,id','group_code'=>['required',Rule::in(self::GROUPS)],'target'=>'required|integer|min:1|max:1000000']);$ver=DB::table('quota_configs')->where('site_id',$d['site_id'])->where('wave_id',$d['wave_id'])->where('group_code',$d['group_code'])->max('version')+1;$id=DB::table('quota_configs')->insertGetId($d+['version'=>$ver,'status'=>'draft','submitted_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);Audit::log('quota.drafted','quota_configs',$id);return back()->with('ok','Đã tạo quota nháp; chưa có hiệu lực.');}
 public function submitQuota(int $id) {$q=DB::table('quota_configs')->find($id);abort_unless($q && $q->status==='draft',422);DB::table('quota_configs')->where('id',$id)->update(['status'=>'pending_review','submitted_by'=>auth()->id(),'updated_at'=>now()]);Audit::log('quota.submitted','quota_configs',$id);return back()->with('ok','Đã gửi duyệt quota.');}
 public function approveQuota(int $id) {$q=DB::table('quota_configs')->find($id);abort_unless($q && $q->status==='pending_review',422);abort_if($q->submitted_by===auth()->id(),403,'Không được tự phê duyệt cấu hình của chính mình.');DB::transaction(function()use($q,$id){DB::table('quota_configs')->where('site_id',$q->site_id)->where('wave_id',$q->wave_id)->where('group_code',$q->group_code)->where('status','approved')->update(['status'=>'superseded','updated_at'=>now()]);DB::table('quota_configs')->where('id',$id)->update(['status'=>'approved','approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);Audit::log('quota.approved','quota_configs',$id);});return back()->with('ok','Đã phê duyệt quota.');}
 public function information() {return view('admin.information',['versions'=>DB::table('information_versions')->orderByDesc('version')->get()]);}
 public function addInformation(Request $r) {$d=$r->validate(['purpose'=>'required|string|max:10000','activities'=>'required|string|max:10000','duration'=>'required|string|max:4000','voluntary'=>'required|string|max:4000','withdrawal'=>'required|string|max:4000','contact'=>'required|string|max:4000','data_use'=>'required|string|max:10000']);$ver=(DB::table('information_versions')->max('version')??0)+1;$id=DB::table('information_versions')->insertGetId($d+['version'=>$ver,'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);Audit::log('information.drafted','information_versions',$id);return back()->with('ok','Đã lưu phiên bản nháp.');}
 public function publishInformation(int $id) {$v=DB::table('information_versions')->find($id);abort_unless($v && $v->status==='draft',422);DB::transaction(function()use($id){DB::table('information_versions')->where('status','published')->update(['status'=>'superseded','updated_at'=>now()]);DB::table('information_versions')->where('id',$id)->update(['status'=>'published','approved_by'=>auth()->id(),'published_at'=>now(),'updated_at'=>now()]);Audit::log('information.published','information_versions',$id);});return back()->with('ok','Thông tin người tham gia đã được công bố.');}
 public function surveys() {return view('admin.surveys',['surveys'=>DB::table('surveys')->orderBy('group_code')->orderByDesc('version')->get()]);}
 public function addSurvey(Request $r) {$d=$r->validate(['group_code'=>['required',Rule::in(self::GROUPS)],'title'=>'required|string|max:200','instrument_id'=>['nullable',Rule::in(['OBS2','OBS4'])],'form_version'=>['nullable','string','max:64',Rule::unique('surveys','form_version')->where('instrument_id',$r->input('instrument_id'))->where('group_code',$r->input('group_code'))]]);if(in_array($d['group_code'],['BGH','KHOA','GV','SV'],true)){if(empty($d['instrument_id'])||empty($d['form_version'])||($d['instrument_id']==='OBS2'&&!in_array($d['group_code'],['BGH','KHOA'],true)))return back()->withErrors(['instrument_id'=>'Cần bộ phiếu và phiên bản đúng nhóm; OBS2 chỉ BGH/KHOA.']);}$d['version']=(DB::table('surveys')->where('group_code',$d['group_code'])->max('version')??0)+1;$id=DB::table('surveys')->insertGetId($d+['status'=>'draft','created_at'=>now(),'updated_at'=>now()]);Audit::log('survey.drafted','surveys',$id);return redirect()->route('admin.survey.edit',$id)->with('ok','Bản nháp bảng hỏi đã được tạo.');}
 public function surveyEdit(int $id) {$s=DB::table('surveys')->find($id);abort_unless($s,404);return view('admin.survey_edit',['survey'=>$s,'questions'=>DB::table('questions')->where('survey_id',$id)->orderBy('position')->get()]);}
 public function addQuestion(Request $r,int $id) {
   $s=DB::table('surveys')->find($id);abort_unless($s && $s->status==='draft',422,'Không được sửa phiên bản đã công bố.');
   $d=$r->validate(['code'=>['required','regex:/^[A-Za-z][A-Za-z0-9_]{0,30}$/D',Rule::unique('questions')->where('survey_id',$id)],'label'=>'required|string|max:1000','type'=>['required',Rule::in(['text','number','single','likert'])],'choices_text'=>'nullable|string|max:10000','condition_code'=>'nullable|string|max:31','condition_value'=>'nullable|string|max:255','required'=>'nullable|boolean','variable_label'=>'nullable|string|max:255','missing_code'=>'nullable|string|max:30','scale'=>'nullable|string|max:255','min_value'=>'nullable|numeric|between:-99999999,99999999','max_value'=>'nullable|numeric|between:-99999999,99999999']);
   if(isset($d['min_value'],$d['max_value'])&&$d['min_value']>$d['max_value'])return back()->withErrors(['max_value'=>'Giá trị tối đa nhỏ hơn tối thiểu.']);
   $choices=[];if(in_array($d['type'],['single','likert'],true)) {foreach(preg_split('/\r?\n/',trim($d['choices_text']??'')) as $line){if(!strlen(trim($line)))continue;[$val,$lab]=array_pad(explode('=',trim($line),2),2,'');if(!preg_match('/^[A-Za-z0-9_-]{1,32}$/',trim($val)) || !strlen(trim($lab)))return back()->withErrors(['choices_text'=>'Mỗi dòng lựa chọn phải theo mẫu ma=nhãn; mã không có khoảng trắng.']);if(array_key_exists(trim($val),$choices))return back()->withErrors(['choices_text'=>'Mã lựa chọn bị trùng trong cùng câu hỏi.']);$choices[trim($val)]=trim($lab);}if(!$choices)return back()->withErrors(['choices_text'=>'Cần ít nhất một lựa chọn.']);}
   $condition=null;if(($d['condition_code']??'')!==''){$dep=DB::table('questions')->where('survey_id',$id)->where('code',$d['condition_code'])->first();if(!$dep||!isset($d['condition_value'])||$d['condition_value']==='')return back()->withErrors(['condition_code'=>'Điều kiện phải tham chiếu câu có trước và có giá trị.']);if(!in_array($dep->type,['single','likert'],true)||!array_key_exists((string)$d['condition_value'],json_decode($dep->choices??'{}',true)?:[]))return back()->withErrors(['condition_value'=>'Giá trị điều kiện phải là một mã lựa chọn đã có của câu trước.']);$condition=['code'=>$d['condition_code'],'equals'=>$d['condition_value']];}
   $qid=DB::table('questions')->insertGetId(['survey_id'=>$id,'code'=>$d['code'],'label'=>$d['label'],'type'=>$d['type'],'choices'=>json_encode($choices,JSON_UNESCAPED_UNICODE),'condition'=>json_encode($condition),'required'=>(bool)($d['required']??false),'position'=>((int)DB::table('questions')->where('survey_id',$id)->max('position'))+1,'variable_label'=>$d['variable_label']??$d['label'],'missing_code'=>$d['missing_code']??null,'scale'=>$d['scale']??null,'min_value'=>$d['type']==='number'?($d['min_value']??null):null,'max_value'=>$d['type']==='number'?($d['max_value']??null):null,'created_at'=>now(),'updated_at'=>now()]);Audit::log('question.created','questions',$qid);return back()->with('ok','Đã thêm câu hỏi.');
 }
 public function updateQuestion(Request $r,int $id,int $questionId) {
   $survey=DB::table('surveys')->find($id);
   abort_unless($survey && $survey->status==='draft',422,'Không được sửa câu hỏi của phiên bản đã công bố.');
   $q=DB::table('questions')->where('survey_id',$id)->where('id',$questionId)->first();abort_unless($q,404);
   $d=$r->validate(['label'=>'required|string|max:1000','choices_text'=>'nullable|string|max:10000',
     'required'=>'nullable|boolean','variable_label'=>'nullable|string|max:255','missing_code'=>'nullable|string|max:30',
     'scale'=>'nullable|string|max:255','min_value'=>'nullable|numeric|between:-99999999,99999999',
     'max_value'=>'nullable|numeric|between:-99999999,99999999','condition_code'=>'nullable|string|max:31',
     'condition_value'=>'nullable|string|max:255']);
   if(isset($d['min_value'],$d['max_value']) && $d['min_value']>$d['max_value'])return back()->withErrors(['max_value'=>'Giá trị tối đa nhỏ hơn tối thiểu.']);
   $choices=[];
   if(in_array($q->type,['single','likert'],true)){
     foreach(preg_split('/\r?\n/',trim($d['choices_text']??'')) as $line){
       if(trim($line)==='')continue;
       [$code,$name]=array_pad(explode('=',trim($line),2),2,'');$code=trim($code);$name=trim($name);
       if(!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$code) || $name==='')return back()->withErrors(['choices_text'=>'Mỗi dòng phải theo mẫu ma=nhãn.']);
       if(array_key_exists($code,$choices))return back()->withErrors(['choices_text'=>'Trùng mã lựa chọn.']);
       $choices[$code]=$name;
     }
     if(!$choices)return back()->withErrors(['choices_text'=>'Câu dạng lựa chọn phải có lựa chọn.']);
     // A later question may branch on this choice: never silently invalidate that rule.
     foreach(DB::table('questions')->where('survey_id',$id)->where('position','>',$q->position)->get() as $later){
       $cond=json_decode($later->condition??'null',true);
       if(($cond['code']??null)===$q->code && !array_key_exists((string)($cond['equals']??''),$choices))
         return back()->withErrors(['choices_text'=>'Một câu sau đang tham chiếu mã lựa chọn đã bỏ. Hãy giữ mã này hoặc cập nhật nhánh phụ thuộc trước.']);
     }
   }
   $condition=null;
   if(!empty($d['condition_code'])){
     $previous=DB::table('questions')->where('survey_id',$id)->where('code',$d['condition_code'])->where('position','<',$q->position)->first();
     if(!$previous || !in_array($previous->type,['single','likert'],true))return back()->withErrors(['condition_code'=>'Điều kiện phải tham chiếu câu lựa chọn xuất hiện trước.']);
     if(!array_key_exists((string)($d['condition_value']??''),json_decode($previous->choices??'{}',true)?:[]))return back()->withErrors(['condition_value'=>'Mã lựa chọn dùng làm điều kiện không tồn tại.']);
     $condition=['code'=>$previous->code,'equals'=>$d['condition_value']];
   }
   DB::table('questions')->where('id',$q->id)->update(['label'=>$d['label'],'choices'=>json_encode($choices,JSON_UNESCAPED_UNICODE),
     'condition'=>json_encode($condition),'required'=>(bool)($d['required']??false),
     'variable_label'=>$d['variable_label']??$d['label'],'missing_code'=>$d['missing_code']??null,
     'scale'=>$d['scale']??null,'min_value'=>$q->type==='number'?($d['min_value']??null):null,
     'max_value'=>$q->type==='number'?($d['max_value']??null):null,'updated_at'=>now()]);
   Audit::log('question.updated','questions',$q->id,['survey_id'=>$id]);
   return back()->with('ok','Đã cập nhật câu hỏi trong phiên bản nháp.');
 }
 public function deleteQuestion(int $id,int $questionId) {
   $survey=DB::table('surveys')->find($id);
   abort_unless($survey && $survey->status==='draft',422,'Không được xóa câu hỏi đã công bố.');
   $question=DB::table('questions')->where('survey_id',$id)->where('id',$questionId)->first();abort_unless($question,404);
   foreach(DB::table('questions')->where('survey_id',$id)->where('id','!=',$questionId)->get() as $other){
     $condition=json_decode($other->condition??'null',true);
     if(($condition['code']??null)===$question->code)return back()->withErrors(['question'=>'Không thể xóa vì câu hỏi khác đang phụ thuộc rẽ nhánh.']);
   }
   DB::table('questions')->where('id',$questionId)->delete();
   Audit::log('question.deleted','questions',$questionId,['survey_id'=>$id,'code'=>$question->code]);
   return back()->with('ok','Đã xóa câu hỏi trong bản nháp.');
 }
 public function cloneSurvey(int $id) {
   $source=DB::table('surveys')->find($id);
   abort_unless($source && in_array($source->status,['published','superseded'],true),422,'Chỉ sao chép phiên bản đã phát hành.');
   $new=DB::transaction(function()use($source){
     $version=((int) DB::table('surveys')->where('group_code',$source->group_code)->max('version'))+1;
     $newId=DB::table('surveys')->insertGetId(['group_code'=>$source->group_code,'version'=>$version,'title'=>$source->title,'instrument_id'=>$source->instrument_id,'form_version'=>null,'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
     foreach(DB::table('questions')->where('survey_id',$source->id)->orderBy('position')->get() as $q){
       DB::table('questions')->insert(['survey_id'=>$newId,'code'=>$q->code,'source_item_code'=>$q->source_item_code,'construct_name'=>$q->construct_name,'source_ref'=>$q->source_ref,'label'=>$q->label,'type'=>$q->type,'choices'=>$q->choices,'condition'=>$q->condition,'required'=>$q->required,'position'=>$q->position,'variable_label'=>$q->variable_label,'missing_code'=>$q->missing_code,'scale'=>$q->scale,'min_value'=>$q->min_value,'max_value'=>$q->max_value,'created_at'=>now(),'updated_at'=>now()]);
     }
     Audit::log('survey.cloned','surveys',$newId,['source_survey_id'=>$source->id,'source_version'=>$source->version]);
     return $newId;
   });
   return redirect()->route('admin.survey.edit',$new)->with('ok','Đã sao chép thành bản nháp mới. Phiên bản trước không thay đổi.');
 }
 public function publishSurvey(int $id) {$s=DB::table('surveys')->find($id);abort_unless($s && $s->status==='draft',422);abort_unless(DB::table('questions')->where('survey_id',$id)->exists(),422,'Chưa có câu hỏi.');if(in_array($s->group_code,['BGH','KHOA','GV','SV'],true)){abort_unless($s->instrument_id && $s->form_version,422,'Cần metadata bộ phiếu và phiên bản.');}DB::transaction(function()use($id,$s){DB::table('surveys')->where('group_code',$s->group_code)->where('status','published')->update(['status'=>'superseded','updated_at'=>now()]);DB::table('surveys')->where('id',$id)->update(['status'=>'published','published_at'=>now(),'approved_by'=>auth()->id(),'updated_at'=>now()]);Audit::log('survey.published','surveys',$id);});return back()->with('ok','Bảng hỏi đã xuất bản và khóa chỉnh sửa.');}
 public function users() {return view('admin.users',['users'=>User::orderBy('id')->get(),'sites'=>DB::table('sites')->orderBy('code')->get()]);}
 public function addUser(Request $r){$d=$r->validate(['name'=>'required|string|max:200','email'=>'required|email|unique:users,email','password'=>'required|string|min:12','role'=>['required',Rule::in(['owner','researcher','quota_manager','qc_reviewer','data_manager','viewer','NCS','IT_ADMIN','ANALYST','SITE_COORDINATOR','AUDITOR'])],'assigned_site_id'=>'nullable|required_if:role,SITE_COORDINATOR|exists:sites,id']);$u=User::create($d);Audit::log('user.created','users',$u->id,['role'=>$u->role]);return back()->with('ok','Đã thêm người dùng.');}
 public function toggleUser(int $id) {
   $user=User::findOrFail($id);
   abort_if($user->id===auth()->id(),403,'Không được tự khóa tài khoản đang sử dụng.');
   $user->active=!$user->active;
   $user->save();
   Audit::log('user.active_toggled','users',$user->id,['active'=>$user->active]);
   return back()->with('ok',$user->active?'Đã mở tài khoản.':'Đã khóa tài khoản.');
 }
 public function requests() {return view('admin.requests',['requests'=>DB::table('participant_requests as r')->join('participants as p','r.participant_id','=','p.id')->select('r.*','p.research_id')->orderByDesc('r.id')->get()]);}
 public function resolveRequest(Request $r,int $id) {
  $req=DB::table('participant_requests')->find($id);abort_unless($req&&$req->status!=='resolved',422);
  $rules=['resolution'=>'required|string|max:10000'];
  if($req->type==='withdrawal')$rules['disposition']='required|in:exclude,retain';
  $d=$r->validate($rules);
  DB::transaction(function()use($req,$id,$d){
    $locked=DB::table('participant_requests')->where('id',$id)->lockForUpdate()->first();
    abort_unless($locked && $locked->status!=='resolved',422);
    DB::table('participant_requests')->where('id',$id)->where('status','!=','resolved')->update(['status'=>'resolved','resolution'=>$d['resolution'],'handled_by'=>auth()->id(),'updated_at'=>now()]);
    if($req->type==='withdrawal'){
      // Exclusion concerns analytical use; raw records remain for audited retention under the research protocol.
      $stillPending=DB::table('participant_requests')->where('participant_id',$req->participant_id)->where('type','withdrawal')->where('status','!=','resolved')->exists();
      $state=$stillPending?'withdrawal_requested':($d['disposition']==='exclude'?'withdrawn':'retained_by_protocol');
      DB::table('participants')->where('id',$req->participant_id)->update(['state'=>$state,'updated_at'=>now()]);
    }
    Audit::log('request.resolved','participant_requests',$id,['type'=>$req->type,'disposition'=>$d['disposition']??null]);
  });
  return back()->with('ok','Đã ghi nhận quyết định; dữ liệu gốc không bị tự xóa.');
 }
 public function qr(int $id) {
  $link=DB::table('sample_links')->find($id);abort_unless($link,404);
  abort_if($link->token_hashed,410,'QR chỉ được cấp một lần khi tạo link.');$url=route('portal.intro',$link->token);
  $qr=new \Endroid\QrCode\QrCode(data:$url,size:280,margin:12);
  $svg=(new \Endroid\QrCode\Writer\SvgWriter())->write($qr)->getString();
  return response($svg,200,['Content-Type'=>'image/svg+xml','Content-Disposition'=>'inline; filename="research-link-'.$id.'.svg"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function snapshot(){
   [$bytes,$count]=\App\Services\SnapshotService::build();
   Audit::log('dashboard.snapshot','quota_configs',null,['approved_quotas'=>$count]);
   return response($bytes,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="quota_qc_snapshot_'.now()->format('Ymd_His').'.csv"','Cache-Control'=>'private, no-store']);
 }

 public function reportSnapshots(){
  return view('admin.report_snapshots',['reports'=>DB::table('report_snapshots')->orderByDesc('id')->limit(200)->get()]);
 }
 public function downloadReport(int $id){
  $report=DB::table('report_snapshots')->find($id);abort_unless($report,404);
  $disk=\Illuminate\Support\Facades\Storage::disk('local');abort_unless($disk->exists($report->storage_path),404);
  $bytes=$disk->get($report->storage_path);abort_unless(hash_equals($report->sha256,hash('sha256',$bytes)),409,'Checksum không khớp, cần kiểm tra bản snapshot.');
  Audit::log('dashboard.snapshot_read','report_snapshots',$id);
  return response($bytes,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="research_snapshot_'.$id.'.csv"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function audit(){return view('admin.audit',['logs'=>DB::table('audit_events as a')->leftJoin('users as u','a.actor_id','=','u.id')->select('a.*','u.email')->orderByDesc('a.id')->limit(300)->get()]);}
}
