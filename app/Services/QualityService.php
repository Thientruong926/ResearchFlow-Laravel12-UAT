<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class QualityService {
 public function evaluate(int $sessionId): void {
  $session=DB::table('survey_sessions')->find($sessionId);if(!$session||$session->status!=='complete')return;
  $qs=DB::table('questions')->where('survey_id',$session->survey_id)->get();$as=DB::table('answers')->where('survey_session_id',$sessionId)->get()->keyBy('question_id');
  $values=[];$missing=0;$eligible=0;$byCode=[];foreach($qs as $q){$cond=json_decode($q->condition??'null',true);$visible=!$cond||($byCode[$cond['code']]??null)===$cond['equals'];if(!$visible){$byCode[$q->code]=null;continue;}$eligible++;$raw=$as->get($q->id)?->value;$val=$raw?json_decode($raw,true):null;$byCode[$q->code]=$val;if($val===null||$val==='')$missing++;if($q->type==='likert'&&$val!==null)$values[]=(string)$val;}
  $rules=DB::table('quality_rules')->where('status','approved')->get();
  foreach($rules as $rule) {
   $flag=false;$evidence='';$threshold=(float)$rule->threshold;
   if($rule->code==='too_fast'){$seconds=$session->completed_at && $session->started_at?max(0,\Carbon\Carbon::parse($session->started_at)->diffInSeconds($session->completed_at)):null;$flag=$seconds!==null&&$seconds<$threshold;$evidence='duration_seconds='.($seconds??'unknown');}
   if($rule->code==='missing_pct'){$pct=$eligible?100*$missing/$eligible:0;$flag=$pct>$threshold;$evidence='missing_pct='.round($pct,2);}
   if($rule->code==='straightlining_pct'){$pct=count($values)?100*max(array_count_values($values))/count($values):0;$flag=count($values)>=3&&$pct>=$threshold;$evidence='dominant_response_pct='.round($pct,2);}
   if($rule->code==='logic_conflict'){
     $pairs=json_decode($rule->definition,true);$conflicts=[];
     foreach(is_array($pairs)?$pairs:[] as $pair){
       if(!isset($pair['left'],$pair['right'],$pair['left_value'],$pair['right_value']))continue;
       if(($byCode[$pair['left']]??null)!==null && ($byCode[$pair['right']]??null)!==null
           && (string)$byCode[$pair['left']]===(string)$pair['left_value']
           && (string)$byCode[$pair['right']]===(string)$pair['right_value'])$conflicts[]=$pair['left'].'/'.$pair['right'];
     }
     $flag=count($conflicts)>0;$evidence='approved_conflicts='.implode(',',$conflicts);
   }
   if($flag) DB::table('quality_flags')->updateOrInsert(['survey_session_id'=>$sessionId,'quality_rule_id'=>$rule->id],['evidence'=>$evidence,'updated_at'=>now(),'created_at'=>now()]);
  }
 }
 public function reusedToken(int $sessionId):void {
   $rule=DB::table('quality_rules')->where('status','approved')->where('code','duplicate_token')->first();
   if($rule)DB::table('quality_flags')->updateOrInsert(['survey_session_id'=>$sessionId,'quality_rule_id'=>$rule->id],['evidence'=>'Repeated submission attempt for same resume token; not proof of duplicate person','updated_at'=>now(),'created_at'=>now()]);
 }
}
