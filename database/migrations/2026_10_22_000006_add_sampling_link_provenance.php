<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sample_links', function (Blueprint $table) {
            $table->string('faculty_code', 32)->nullable();
            $table->string('class_code', 32)->nullable();
        });
        Schema::table('participants', function (Blueprint $table) {
            // Historical records cannot be attributed to a specific invitation reliably.
            $table->foreignId('sample_link_id')->nullable()->constrained('sample_links');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sample_link_id');
        });
        Schema::table('sample_links', function (Blueprint $table) {
            $table->dropColumn(['faculty_code', 'class_code']);
        });
    }
};
