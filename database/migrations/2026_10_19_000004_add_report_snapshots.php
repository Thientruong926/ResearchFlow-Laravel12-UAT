<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('report_snapshots',function(Blueprint $t){
  $t->id();$t->string('storage_path');$t->unsignedInteger('quota_rows');$t->string('sha256',64);$t->timestamp('created_at');
 });}
 public function down():void {Schema::dropIfExists('report_snapshots');}
};
