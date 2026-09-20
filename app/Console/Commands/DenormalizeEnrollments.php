<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DenormalizeEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enrollments:denormalize-backfill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting backfill for denormalized columns in enrollments...");
        $startTime = microtime(true);
        $totalProcessed = 0;

        \App\Models\Enrollment::select('id')
            ->whereNull('student_nim')
            ->chunkById(10000, function ($enrollments) use (&$totalProcessed) {
                $ids = $enrollments->pluck('id')->implode(',');
                
                \Illuminate\Support\Facades\DB::statement("
                    UPDATE enrollments e
                    INNER JOIN students s ON e.student_id = s.id
                    INNER JOIN courses c ON e.course_id = c.id
                    SET e.student_nim = s.nim,
                        e.student_name = s.name,
                        e.course_code = c.code,
                        e.course_name = c.name
                    WHERE e.id IN ($ids)
                ");
                
                $totalProcessed += $enrollments->count();
                $this->info("Processed {$totalProcessed} records...");
            });

        $executionTime = microtime(true) - $startTime;
        $this->info("Backfill completed successfully in " . round($executionTime, 2) . " seconds.");
        
        $nullCount = \App\Models\Enrollment::whereNull('student_nim')->count();
        $this->info("Sanity check - Rows with NULL student_nim: " . $nullCount);
    }
}
