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
    private function buildQuery(Request $request)
    {
        $query = Enrollment::query()->with(['student', 'course']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $studentIds = Student::where('nim', 'like', "{$search}%")->orWhere('name', 'like', "{$search}%")->pluck('id');
            $courseIds = Course::where('code', 'like', "{$search}%")->orWhere('name', 'like', "{$search}%")->pluck('id');
            
            $query->where(function ($q) use ($studentIds, $courseIds) {
                if ($studentIds->isNotEmpty()) {
                    $q->whereIn('student_id', $studentIds);
                }
                if ($courseIds->isNotEmpty()) {
                    $q->orWhereIn('course_id', $courseIds);
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

                        if (in_array($field, ['nim', 'name'])) {
                            $studentIds = Student::where($field, $dbOp, $dbVal)->pluck('id');
                            $q->{$logic . 'In'}('student_id', $studentIds);
                        } elseif (in_array($field, ['code'])) {
                            $courseIds = Course::where($field, $dbOp, $dbVal)->pluck('id');
                            $q->{$logic . 'In'}('course_id', $courseIds);
                        } else {
                            $q->$logic($field, $dbOp, $dbVal);
                        }
                    }
                });
            }
        }
        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->buildQuery($request);

        // Menghitung summary stats berdasarkan filter yang sedang aktif
        $statsQuery = clone $query;
        $statusCounts = $statsQuery->select('status', DB::raw('count(*) as total'))
                                   ->groupBy('status')
                                   ->pluck('total', 'status');

        // Multi-kolom sort: sort_orders=[{"col":"id","dir":"desc"},{"col":"status","dir":"asc"}]
        // Backward-compat: sort_by & sort_dir (single-column)
        $allowedSorts = ['academic_year', 'semester', 'status', 'id', 'created_at'];

        if ($request->filled('sort_orders')) {
            $sortOrders = json_decode($request->sort_orders, true);
            if (is_array($sortOrders)) {
                foreach ($sortOrders as $order) {
                    $col = $order['col'] ?? '';
                    $dir = ($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    if (in_array($col, $allowedSorts)) {
                        $query->orderBy($col, $dir);
                    }
                }
            }
        } elseif ($request->filled('sort_by') && in_array($request->sort_by, $allowedSorts)) {
            $dir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort_by, $dir);
        } else {
            $query->orderBy('id', 'desc');
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
        $query = $this->buildQuery($request);

        $response = new StreamedResponse(function() use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['NIM', 'Nama Mahasiswa', 'Kode MK', 'Nama MK', 'Semester', 'Tahun Ajaran', 'Status']);

            $query->chunkById(5000, function ($enrollments) use ($handle) {
                foreach ($enrollments as $enrollment) {
                    fputcsv($handle, [
                        $enrollment->student->nim,
                        $enrollment->student->name,
                        $enrollment->course->code,
                        $enrollment->course->name,
                        $enrollment->semester,
                        $enrollment->academic_year,
                        $enrollment->status
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="enrollments.csv"');

        return $response;
    }
}
