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
        echo "No queries run.\n\n";
        return;
    }

    foreach($logs as $log) {
        $sql = $log['query'];
        $bindings = $log['bindings'];
        
        // Convert bindings for EXPLAIN
        $fullSql = $sql;
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
            $fullSql = preg_replace('/\?/', $value, $fullSql, 1);
        }
        
        echo "SQL: $fullSql\n";
        
        if (strpos(strtoupper($fullSql), 'SELECT') === 0) {
            try {
                $explain = DB::select("EXPLAIN $fullSql");
                echo "EXPLAIN:\n";
                foreach ($explain as $row) {
                    echo "  Table: {$row->table}, Type: {$row->type}, Possible Keys: {$row->possible_keys}, Key: {$row->key}, Rows: {$row->rows}, Extra: {$row->Extra}\n";
                }
            } catch (\Exception $e) {
                echo "EXPLAIN Failed: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "\n";
}

// 1. Search Query (whereHas)
measure("Search with whereHas", function() {
    Enrollment::where(function ($q) {
        $search = '123';
        $q->whereHas('student', function ($sq) use ($search) {
            $sq->where('nim', 'like', "%{$search}%")
               ->orWhere('name', 'like', "%{$search}%");
        })->orWhereHas('course', function ($cq) use ($search) {
            $cq->where('code', 'like', "%{$search}%");
        });
    })->limit(15)->get();
});

// 2. Count for Pagination
measure("Count for Pagination", function() {
    Enrollment::count();
});

// 3. Quick Filter (Status)
measure("Quick Filter Status", function() {
    Enrollment::where('status', 'DRAFT')->limit(15)->get();
});

// 4. Sort by id
measure("Sort by ID", function() {
    Enrollment::orderBy('id', 'desc')->limit(15)->get();
});
