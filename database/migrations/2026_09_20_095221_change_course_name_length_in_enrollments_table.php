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
            $table->string('course_name', 120)->nullable()->change();
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
            $table->string('course_name', 100)->nullable()->change();
        });
    }
};
