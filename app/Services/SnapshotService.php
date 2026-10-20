<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
/** Periodic QC/quota report. Only aggregate site codes; no participant IDs. */
class SnapshotService {
 public static function build():array {
  $records=DB::table('quota_configs as q')->join('sites as s','q.site_id','=','s.id')->join('waves as w','q.wave_id','=','w.id')->select('q.*','s.code as site','w.code as wave')->where('q.status','approved')->orderBy('s.code')->orderBy('w.code')->get();
  $h=fopen('php://temp','w+');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,['SITE_CODE','WAVE_CODE','GROUP','TARGET','COMPLETED','VALID','REVIEW','EXCLUDE','UNREVIEWED','QC_FLAGS','DELTA','SNAPSHOT_AT'],',','"','');
  foreach($records as $q){
   $sessions=DB::table('survey_sessions as ss')->join('participants as p','ss.participant_id','=','p.id')->where('p.site_id',$q->site_id)->where('p.wave_id',$q->wave_id)->where('p.group_code',$q->group_code)->where('ss.status','complete')->pluck('ss.id');
   $stats=['valid'=>0,'review'=>0,'exclude'=>0,'unreviewed'=>0];$flagCount=0;
   foreach($sessions as $id){$state=DB::table('qc_decisions')->where('survey_session_id',$id)->orderByDesc('id')->value('decision')??'unreviewed';$stats[$state]++;$flagCount+=DB::table('quality_flags')->where('survey_session_id',$id)->count();}
   $n=$sessions->count();fputcsv($h,[$q->site,$q->wave,$q->group_code,$q->target,$n,$stats['valid'],$stats['review'],$stats['exclude'],$stats['unreviewed'],$flagCount,$n-$q->target,now()->toDateTimeString()],',','"','');
  }
  rewind($h);$bytes=stream_get_contents($h);fclose($h);
  return [$bytes,$records->count()];
 }
}
