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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('status', 'enrollments_status_index_new');
            $table->index('semester', 'enrollments_semester_index_new');
            $table->index('academic_year', 'enrollments_academic_year_index_new');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_status_index_new');
            $table->dropIndex('enrollments_semester_index_new');
            $table->dropIndex('enrollments_academic_year_index_new');
        });
    }
};
