<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('technical_duplicate_flags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('survey_session_id')->constrained();
            $t->string('rule_code', 32);
            $t->string('evidence_hash', 64);
            $t->string('status', 16)->default('OPEN');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['survey_session_id', 'rule_code']);
        });
        Schema::table('answers', fn (Blueprint $t) => $t->string('missing_code', 8)->nullable());
        Schema::table('questions', function (Blueprint $t) {
            $t->string('source_item_code', 64)->nullable();
            $t->string('construct_name')->nullable();
            $t->string('source_ref')->nullable();
            $t->text('label')->change();
        });
    }

    public function down(): void
    {
        Schema::table('answers', fn (Blueprint $t) => $t->dropColumn('missing_code'));
        Schema::table('questions', fn (Blueprint $t) => $t->dropColumn(['source_item_code', 'construct_name', 'source_ref']));
        Schema::dropIfExists('technical_duplicate_flags');
    }
};
