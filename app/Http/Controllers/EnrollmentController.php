<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Course;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentController extends Controller
{
    private function buildQuery(Request $request, &$needsJoin = false)
    {
        $query = Enrollment::query()->with(['student', 'course']);

        // Check if we need to join for sorting
        $sorts = [];
        if ($request->filled('sort_orders')) {
            $sortOrders = json_decode($request->sort_orders, true);
            if (is_array($sortOrders)) $sorts = $sortOrders;
        } elseif ($request->filled('sort_by')) {
            $sorts[] = ['col' => $request->sort_by];
        }

        foreach ($sorts as $s) {
            $col = $s['col'] ?? '';
            if (in_array($col, ['nim', 'student_name', 'code', 'course_name'])) {
                $needsJoin = true;
                break;
            }
        }

        if ($needsJoin) {
            $query->select('enrollments.*')
                  ->join('students', 'enrollments.student_id', '=', 'students.id')
                  ->join('courses', 'enrollments.course_id', '=', 'courses.id');
        }

        if ($request->filled('status')) {
            $query->where('enrollments.status', $request->status);
        }
        if ($request->filled('semester')) {
            $query->where('enrollments.semester', $request->semester);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $studentIds = Student::where('nim', 'like', "{$search}%")->orWhere('name', 'like', "{$search}%")->pluck('id');
            $courseIds = Course::where('code', 'like', "{$search}%")->orWhere('name', 'like', "{$search}%")->pluck('id');
            
            $query->where(function ($q) use ($studentIds, $courseIds) {
                if ($studentIds->isNotEmpty()) {
                    $q->whereIn('enrollments.student_id', $studentIds);
                }
                if ($courseIds->isNotEmpty()) {
                    $q->orWhereIn('enrollments.course_id', $courseIds);
                }
                if ($studentIds->isEmpty() && $courseIds->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });
        }
        if ($request->filled('filters')) {
            $filters = json_decode($request->filters, true);
            if (is_array($filters) && count($filters) > 0) {
                $logic = $request->input('filter_logic', 'and') === 'or' ? 'orWhere' : 'where';
                
                $query->where(function ($q) use ($filters, $logic) {
                    foreach ($filters as $filter) {
                        $field = $filter['field'] ?? '';
                        $op = $filter['operator'] ?? 'equal';
                        $val = $filter['value'] ?? '';
                        
                        if (empty($field)) continue;

                        $dbOp = '=';
                        $dbVal = $val;
                        switch ($op) {
                            case 'contains': $dbOp = 'like'; $dbVal = "{$val}%"; break;
                            case 'startsWith': $dbOp = 'like'; $dbVal = "{$val}%"; break;
                            case 'equal': $dbOp = '='; break;
                        }

                        $isStudentField = in_array($field, ['nim', 'name']);
                        $isCourseField = in_array($field, ['code', 'course_name']);

                        if ($isStudentField || $isCourseField) {
                            $model = $isStudentField ? Student::class : Course::class;
                            $fk = $isStudentField ? 'student_id' : 'course_id';
                            
                            $modelField = $field === 'course_name' ? 'name' : $field;

                            if ($op === 'in' && is_array($val)) {
                                $ids = $model::whereIn($modelField, $val)->pluck('id');
                            } elseif ($op === 'between' && is_array($val) && count($val) == 2) {
                                $ids = $model::whereBetween($modelField, $val)->pluck('id');
                            } else {
                                $ids = $model::where($modelField, $dbOp, $dbVal)->pluck('id');
                            }
                            $q->{$logic . 'In'}("enrollments.{$fk}", $ids);
                        } else {
                            if ($op === 'in' && is_array($val)) {
                                $q->{$logic . 'In'}("enrollments.{$field}", $val);
                            } elseif ($op === 'between' && is_array($val) && count($val) == 2) {
                                $q->{$logic . 'Between'}("enrollments.{$field}", $val);
                            } else {
                                $q->$logic("enrollments.{$field}", $dbOp, $dbVal);
                            }
                        }
                    }
                });
            }
        }
        return $query;
    }

    public function index(Request $request)
    {
        $needsJoin = false;
        $query = $this->buildQuery($request, $needsJoin);

        // Menghitung summary stats berdasarkan filter yang sedang aktif
        $statsQuery = clone $query;
        // Hapus orders dan limit untuk stats
        $statsQuery->getQuery()->orders = [];
        $statusCounts = $statsQuery->select('enrollments.status', DB::raw('count(*) as total'))
                                   ->groupBy('enrollments.status')
                                   ->pluck('total', 'status');

        $allowedSorts = [
            'academic_year' => 'enrollments.academic_year',
            'semester' => 'enrollments.semester',
            'status' => 'enrollments.status',
            'id' => 'enrollments.id',
            'created_at' => 'enrollments.created_at',
            'nim' => 'students.nim',
            'student_name' => 'students.name',
            'code' => 'courses.code',
            'course_name' => 'courses.name'
        ];

        if ($request->filled('sort_orders')) {
            $sortOrders = json_decode($request->sort_orders, true);
            if (is_array($sortOrders)) {
                foreach ($sortOrders as $order) {
                    $col = $order['col'] ?? '';
                    $dir = ($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    if (array_key_exists($col, $allowedSorts)) {
                        $query->orderBy($allowedSorts[$col], $dir);
                    }
                }
            }
        } elseif ($request->filled('sort_by') && array_key_exists($request->sort_by, $allowedSorts)) {
            $dir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($allowedSorts[$request->sort_by], $dir);
        } else {
            $query->orderBy('enrollments.id', 'desc');
        }

        $pageSize = $request->input('page_size', 15);
        $enrollments = $query->simplePaginate($pageSize)->withQueryString();

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->all(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function store(StoreEnrollmentRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $studentId = $request->student_id;
                if (!$studentId) {
                    $student = Student::create([
                        'nim' => $request->student_nim,
                        'name' => $request->student_name,
                        'email' => $request->student_email,
                    ]);
                    $studentId = $student->id;
                }

                $courseId = $request->course_id;
                if (!$courseId) {
                    $course = Course::create([
                        'code' => $request->course_code,
                        'name' => $request->course_name,
                        'credits' => $request->course_credits,
                    ]);
                    $courseId = $course->id;
                }

                $exists = Enrollment::where('student_id', $studentId)
                    ->where('course_id', $courseId)
                    ->where('academic_year', $request->academic_year)
                    ->where('semester', $request->semester)
                    ->exists();

                if ($exists) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'general' => 'Enrollment for this student and course in the specified semester already exists.',
                    ]);
                }

                Enrollment::create([
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'academic_year' => $request->academic_year,
                    'semester' => $request->semester,
                    'status' => $request->status,
                ]);
            });

            return redirect()->back()->with('success', 'Enrollment created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    public function update(UpdateEnrollmentRequest $request, string $id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->update($request->validated());
        return redirect()->back()->with('success', 'Enrollment updated.');
    }

    public function destroy(string $id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->delete();
        return redirect()->back()->with('success', 'Enrollment soft-deleted.');
    }


    public function export(Request $request)
    {
        // Prevent PHP-level timeout (must still respect Railway's 60s proxy limit)
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $needsJoin = false;

        // For export: build a CLEAN query — strip all sort params to avoid
        // ORDER BY conflict with cursor()/chunkById(). Export always goes by
        // natural enrollment ID order.
        $exportRequest = clone $request;

        $query = $this->buildQuery($exportRequest, $needsJoin);

        // CRITICAL FIX: remove ALL existing orderBy clauses from the query.
        // chunkById() or cursor() adds its own ordering. Mixing with a custom
        // ORDER BY (e.g., students.name) causes the chunk cursor to break or
        // loop infinitely on the same first chunk.
        $query->getQuery()->orders = null;

        // If query uses JOIN (from relational sort params), chunkById with a
        // qualified column name ('enrollments.id') may still break on some
        // MySQL versions. Switch to cursor() (LazyCollection) which is
        // ORDER-BY-safe and memory-efficient via PHP generator.
        $usesCursor = $needsJoin;

        $response = new StreamedResponse(function () use ($query, $usesCursor) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            try {
                fputcsv($handle, ['NIM', 'Nama Mahasiswa', 'Kode MK', 'Nama MK', 'Semester', 'Tahun Ajaran', 'Status']);

                if ($usesCursor) {
                    // cursor() = PHP generator, one row in memory at a time. Safe with JOINs.
                    foreach ($query->cursor() as $enrollment) {
                        fputcsv($handle, [
                            $enrollment->student->nim   ?? '',
                            $enrollment->student->name  ?? '',
                            $enrollment->course->code   ?? '',
                            $enrollment->course->name   ?? '',
                            $enrollment->semester,
                            $enrollment->academic_year,
                            $enrollment->status,
                        ]);
                        // Flush every 1000 rows to prevent output buffer buildup
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    }
                } else {
                    // chunkById is safe here: no custom ORDER BY, no JOIN.
                    // Use plain 'id' (not 'enrollments.id') to match the column in result set.
                    $query->chunkById(1000, function ($enrollments) use ($handle) {
                        foreach ($enrollments as $enrollment) {
                            fputcsv($handle, [
                                $enrollment->student->nim   ?? '',
                                $enrollment->student->name  ?? '',
                                $enrollment->course->code   ?? '',
                                $enrollment->course->name   ?? '',
                                $enrollment->semester,
                                $enrollment->academic_year,
                                $enrollment->status,
                            ]);
                        }
                        // Flush after each chunk so bytes reach the client progressively
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    }, 'id');
                }
            } catch (\Throwable $e) {
                // SAFETY NET: log the real error but do NOT let PHP send an HTML exception
                // page into the middle of the CSV stream. Write a clear CSV comment instead.
                \Illuminate\Support\Facades\Log::error('CSV export failed mid-stream', [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ]);
                // Write a clearly-marked error row so the file is obviously broken
                fputcsv($handle, ['### EXPORT_ERROR ###', $e->getMessage(), 'Check laravel.log for details']);
            } finally {
                fclose($handle);
            }
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="enrollments_export.csv"');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable Nginx buffering (Railway uses Nginx)
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

}
