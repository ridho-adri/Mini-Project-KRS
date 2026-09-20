<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (env('APP_ENV') === 'production') {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('student_nim', 20)->nullable()->index('enrollments_student_nim_idx');
            $table->string('student_name', 100)->nullable()->index('enrollments_student_name_idx');
            $table->string('course_code', 20)->nullable()->index('enrollments_course_code_idx');
            $table->string('course_name', 100)->nullable()->index('enrollments_course_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (env('APP_ENV') === 'production') {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_student_nim_idx');
            $table->dropIndex('enrollments_student_name_idx');
            $table->dropIndex('enrollments_course_code_idx');
            $table->dropIndex('enrollments_course_name_idx');
            $table->dropColumn(['student_nim', 'student_name', 'course_code', 'course_name']);
        });
    }
};
