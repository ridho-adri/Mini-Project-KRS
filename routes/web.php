<?php

use App\Http\Controllers\EnrollmentController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Course;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('enrollments.index');
});

Route::get('/enrollments/export', [EnrollmentController::class, 'export'])->name('enrollments.export');
Route::resource('enrollments', EnrollmentController::class)->except(['show', 'create', 'edit']);

Route::get('/students/search', function (Request $request) {
    $q = $request->q;
    if (!$q) return response()->json([]);
    return Student::where('nim', 'like', "{$q}%")
        ->orWhere('name', 'like', "{$q}%")
        ->take(10)->get();
});

Route::get('/courses/search', function (Request $request) {
    $q = $request->q;
    if (!$q) return response()->json([]);
    return Course::where('code', 'like', "{$q}%")
        ->orWhere('name', 'like', "{$q}%")
        ->take(10)->get();
});
