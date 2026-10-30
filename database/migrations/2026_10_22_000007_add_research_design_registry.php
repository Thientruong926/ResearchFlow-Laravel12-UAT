<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Widen without changing previously issued UUIDs.
        Schema::table('participants', fn (Blueprint $t) => $t->string('research_id', 36)->change());
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained();
            $t->string('code', 32)->unique();
            $t->string('private_label')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('surveys', function (Blueprint $t) {
            $t->string('instrument_id', 8)->nullable();
            $t->string('form_version', 64)->nullable();
            $t->unique(['instrument_id', 'form_version', 'group_code'], 'surveys_instrument_form_group_unique');
        });
        Schema::table('waves', function (Blueprint $t) {
            $t->string('instrument_id', 8)->nullable();
            $t->timestamp('valid_from')->nullable();
            $t->timestamp('valid_until')->nullable();
            $t->string('academic_year', 32)->nullable();
            $t->string('semester', 32)->nullable();
        });
        Schema::table('sample_links', function (Blueprint $t) {
            $t->foreignId('department_id')->nullable()->constrained();
            $t->foreignId('survey_id')->nullable()->constrained();
            $t->string('point_code', 32)->nullable();
            $t->boolean('token_hashed')->default(false);
            $t->string('status', 16)->default('legacy');
            $t->string('existing_session_policy', 16)->nullable();
            $t->timestamp('valid_from')->nullable();
            $t->timestamp('valid_until')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('replaced_by')->nullable()->constrained('sample_links');
        });
    }

    public function down(): void
    {
        Schema::table('sample_links', function (Blueprint $t) {
            foreach (['department_id', 'survey_id', 'owner_id', 'created_by', 'approved_by', 'replaced_by'] as $column) {
                $t->dropConstrainedForeignId($column);
            }
            $t->dropColumn(['point_code', 'token_hashed', 'status', 'existing_session_policy', 'valid_from', 'valid_until', 'approved_at']);
        });
        Schema::table('surveys', function (Blueprint $t) {
            $t->dropUnique('surveys_instrument_form_group_unique');
            $t->dropColumn(['instrument_id', 'form_version']);
        });
        Schema::table('waves', fn (Blueprint $t) => $t->dropColumn(['instrument_id', 'valid_from', 'valid_until', 'academic_year', 'semester']));
        Schema::dropIfExists('departments');
    }
};
