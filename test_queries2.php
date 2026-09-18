<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

function measure($name, $queryClosure) {
    DB::enableQueryLog();
    DB::flushQueryLog();
    
    $start = microtime(true);
    $queryClosure();
    $end = microtime(true);
    
    $logs = DB::getQueryLog();
    
    echo "=== [$name] ===\n";
    echo "Time: " . round(($end - $start) * 1000, 2) . " ms\n";
    
    if (empty($logs)) {
        return;
    }

    foreach($logs as $log) {
        $sql = $log['query'];
        $bindings = $log['bindings'];
        
        $fullSql = $sql;
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
            $fullSql = preg_replace('/\?/', $value, $fullSql, 1);
        }
        
        if (strpos(strtoupper($fullSql), 'SELECT') === 0) {
            try {
                $explain = DB::select("EXPLAIN $fullSql");
                echo "EXPLAIN:\n";
                foreach ($explain as $row) {
                    echo "  Table: {$row->table}, Type: {$row->type}, Possible Keys: {$row->possible_keys}, Key: {$row->key}, Rows: {$row->rows}, Extra: {$row->Extra}\n";
                }
            } catch (\Exception $e) { }
        }
    }
    echo "\n";
}

// Sort by Academic Year
measure("Sort by Academic Year", function() {
    Enrollment::orderBy('academic_year', 'desc')->limit(15)->get();
});

// Join approach for search
measure("Search with JOIN", function() {
    Enrollment::select('enrollments.*')
        ->join('students', 'enrollments.student_id', '=', 'students.id')
        ->join('courses', 'enrollments.course_id', '=', 'courses.id')
        ->where(function($q) {
            $q->where('students.nim', 'like', '123%')
              ->orWhere('students.name', 'like', '123%')
              ->orWhere('courses.code', 'like', '123%');
        })->limit(15)->get();
});

// Advanced Filter (code = CS101)
measure("Advanced Filter with Join", function() {
    Enrollment::select('enrollments.*')
        ->join('courses', 'enrollments.course_id', '=', 'courses.id')
        ->where('courses.code', 'CS101')
        ->limit(15)->get();
});
