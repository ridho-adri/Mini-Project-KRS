<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'credits'];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    protected static function booted()
    {
        static::updated(function ($course) {
            $updates = [];
            if ($course->wasChanged('code')) {
                $updates['course_code'] = $course->code;
            }
            if ($course->wasChanged('name')) {
                $updates['course_name'] = $course->name;
            }
            
            if (!empty($updates)) {
                Enrollment::where('course_id', $course->id)->update($updates);
            }
        });
    }
}
