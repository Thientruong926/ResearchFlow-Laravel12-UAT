<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('transcript_review_events',function(Blueprint $t){
  $t->id();$t->foreignId('transcript_id')->constrained();$t->string('status');$t->text('reason');
  $t->foreignId('reviewer_id')->constrained('users');$t->timestamp('created_at');
 });}
 public function down():void {Schema::dropIfExists('transcript_review_events');}
};
