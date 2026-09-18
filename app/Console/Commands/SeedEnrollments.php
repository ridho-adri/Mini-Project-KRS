<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-enrollments {--count=5000000 : The number of enrollments to seed (default: 5000000, use 750000 for Aiven free tier)}';
    protected $description = 'Seed the database with a specific number of enrollments using bulk insert';

    public function handle()
    {
        $target = (int) $this->option('count');
        $this->info("Starting seed for {$target} enrollments...");

        // Seed 50,000 students if needed
        $studentCount = \Illuminate\Support\Facades\DB::table('students')->count();
        if ($studentCount < 50000) {
            $this->info("Seeding students...");
            $studentsData = [];
            $now = date('Y-m-d H:i:s');
            for ($i = $studentCount + 1; $i <= 50000; $i++) {
                $studentsData[] = [
                    'nim' => str_pad($i, 10, '0', STR_PAD_LEFT),
                    'name' => 'Student ' . $i,
                    'email' => 'student' . $i . '@example.com',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($studentsData) >= 5000) {
                    \Illuminate\Support\Facades\DB::table('students')->insert($studentsData);
                    $studentsData = [];
                }
            }
            if (!empty($studentsData)) {
                \Illuminate\Support\Facades\DB::table('students')->insert($studentsData);
            }
        }
        $studentIds = \Illuminate\Support\Facades\DB::table('students')->pluck('id')->toArray();
        $totalStudents = count($studentIds);

        // Seed 200 courses if needed
        $courseCount = \Illuminate\Support\Facades\DB::table('courses')->count();
        if ($courseCount < 200) {
            $this->info("Seeding courses...");
            $coursesData = [];
            $now = date('Y-m-d H:i:s');
            for ($i = $courseCount + 1; $i <= 200; $i++) {
                $coursesData[] = [
                    'code' => 'CS' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'name' => 'Course ' . $i,
                    'credits' => rand(2, 4),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            \Illuminate\Support\Facades\DB::table('courses')->insert($coursesData);
        }
        $courseIds = \Illuminate\Support\Facades\DB::table('courses')->pluck('id')->toArray();
        $totalCourses = count($courseIds);

        $this->info("Seeding enrollments...");
        $batchSize = 5000;
        $batch = [];
        
        $years = ['2020/2021', '2021/2022', '2022/2023', '2023/2024', '2024/2025'];
        $semesters = ['GANJIL', 'GENAP'];
        $statuses = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'];
        
        $startTime = microtime(true);
        $inserted = 0;
        
        // We will loop sequentially to guarantee uniqueness (student_id, course_id, year, semester)
        $studentIndex = 0;
        $courseIndex = 0;
        $yearIndex = 0;
        $semesterIndex = 0;
        $now = date('Y-m-d H:i:s');

        while ($inserted < $target) {
            $batch[] = [
                'student_id' => $studentIds[$studentIndex],
                'course_id' => $courseIds[$courseIndex],
                'academic_year' => $years[$yearIndex],
                'semester' => $semesters[$semesterIndex],
                'status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            
            // Advance indexes to ensure uniqueness
            $semesterIndex++;
            if ($semesterIndex >= count($semesters)) {
                $semesterIndex = 0;
                $yearIndex++;
                if ($yearIndex >= count($years)) {
                    $yearIndex = 0;
                    $courseIndex++;
                    if ($courseIndex >= $totalCourses) {
                        $courseIndex = 0;
                        $studentIndex++;
                        if ($studentIndex >= $totalStudents) {
                            $studentIndex = 0;
                        }
                    }
                }
            }
            
            if (count($batch) >= $batchSize) {
                \Illuminate\Support\Facades\DB::table('enrollments')->insertOrIgnore($batch);
                $inserted += count($batch);
                $batch = [];
                $this->info("Inserted {$inserted} / {$target} ...");
            }
        }
        
        if (!empty($batch)) {
            \Illuminate\Support\Facades\DB::table('enrollments')->insertOrIgnore($batch);
            $inserted += count($batch);
            $this->info("Inserted {$inserted} / {$target} ...");
        }
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $this->info("Seeding completed! Total inserted: {$inserted} in {$duration} seconds.");
        
        // Output COUNT(*) verification as required
        $finalCount = \Illuminate\Support\Facades\DB::table('enrollments')->count();
        $this->info("Final DB COUNT(*) verification: {$finalCount} rows.");
    }
}
