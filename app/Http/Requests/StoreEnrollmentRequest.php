<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'exists:students,id'],
            'student_nim' => ['required_without:student_id', 'nullable', 'string', 'min:8', 'max:12', 'regex:/^[0-9]+$/', 'unique:students,nim'],
            'student_name' => ['required_without:student_id', 'nullable', 'string', 'min:3', 'max:100'],
            'student_email' => ['required_without:student_id', 'nullable', 'email', 'unique:students,email'],
            
            'course_id' => ['nullable', 'exists:courses,id'],
            'course_code' => ['required_without:course_id', 'nullable', 'string', 'regex:/^[A-Z]{2,4}[0-9]{3}$/', 'unique:courses,code'],
            'course_name' => ['required_without:course_id', 'nullable', 'string', 'min:3', 'max:120'],
            'course_credits' => ['required_without:course_id', 'nullable', 'integer', 'min:1', 'max:6'],
            
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:GANJIL,GENAP'],
            'status' => ['required', 'in:DRAFT,SUBMITTED,APPROVED,REJECTED'],
        ];
    }
}
