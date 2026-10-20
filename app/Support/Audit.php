<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class Audit {
 public static function log(string $action, string $entity, ?int $entityId=null, array $meta=[]):void {
    DB::table('audit_events')->insert(['actor_id'=>auth()->id(), 'action'=>$action,'entity'=>$entity,'entity_id'=>$entityId,'metadata'=>json_encode($meta,JSON_UNESCAPED_UNICODE), 'created_at'=>now()]);
 }
}
