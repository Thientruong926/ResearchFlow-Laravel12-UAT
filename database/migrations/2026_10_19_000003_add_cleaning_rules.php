<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('cleaning_rules',function(Blueprint $t){
   $t->id();$t->foreignId('question_id')->constrained('questions');
   $t->string('source_code',64);$t->string('target_code',64)->nullable();
   $t->text('reason');$t->string('status')->default('draft');
   $t->foreignId('created_by')->constrained('users');
   $t->foreignId('approved_by')->nullable()->constrained('users');$t->timestamp('approved_at')->nullable();$t->timestamps();
   $t->index(['question_id','status']);
  });
  Schema::create('cleaning_applications',function(Blueprint $t){
   $t->id();$t->foreignId('export_run_id')->constrained('export_runs');
   $t->foreignId('survey_session_id')->constrained();
   $t->foreignId('question_id')->constrained('questions');
   $t->foreignId('cleaning_rule_id')->constrained('cleaning_rules');
   $t->text('before_value')->nullable();$t->text('after_value')->nullable();$t->timestamp('created_at');
   $t->index(['export_run_id','survey_session_id']);
  });
 }
 public function down():void {Schema::dropIfExists('cleaning_applications');Schema::dropIfExists('cleaning_rules');}
};
