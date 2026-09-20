<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory;
    
    protected $fillable = ['nim', 'name', 'email'];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    protected static function booted()
    {
        static::updated(function ($student) {
            $updates = [];
            if ($student->wasChanged('nim')) {
                $updates['student_nim'] = $student->nim;
            }
            if ($student->wasChanged('name')) {
                $updates['student_name'] = $student->name;
            }
            
            if (!empty($updates)) {
                Enrollment::where('student_id', $student->id)->update($updates);
            }
        });
    }
}
