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
        Schema::table('students', function (Blueprint $table) {
            $table->index('nim');
            $table->index('name');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->index('code');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['nim']);
            $table->dropIndex(['name']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropIndex(['name']);
        });
    }
};
