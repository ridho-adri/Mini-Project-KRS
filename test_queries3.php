<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

function measure($name, $queryClosure) {
    DB::enableQueryLog();
    DB::flushQueryLog();
    
    $start = microtime(true);
    $queryClosure();
    $end = microtime(true);
    
    echo "=== [$name] ===\n";
    echo "Time: " . round(($end - $start) * 1000, 2) . " ms\n";
}

measure("Search with whereIn", function() {
    $search = '123';
    $studentIds = Student::where('nim', 'like', "{$search}%")->orWhere('name', 'like', "{$search}%")->pluck('id');
    $courseIds = Course::where('code', 'like', "{$search}%")->orWhere('name', 'like', "{$search}%")->pluck('id');
    
    Enrollment::with(['student', 'course'])
        ->where(function ($q) use ($studentIds, $courseIds) {
            if ($studentIds->isNotEmpty()) {
                $q->whereIn('student_id', $studentIds);
            }
            if ($courseIds->isNotEmpty()) {
                $q->orWhereIn('course_id', $courseIds);
            }
            // If both empty, ensure it returns empty
            if ($studentIds->isEmpty() && $courseIds->isEmpty()) {
                $q->whereRaw('1 = 0');
            }
        })
        ->limit(15)->get();
});
