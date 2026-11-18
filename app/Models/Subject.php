<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function limitFor(User $user): int
    {
        return Enrollment::where('user_id', $user->id)->where('course_id', $this->course_id)->value('attempt_limit') ?? $this->max_attempts ?? $this->course->max_attempts;
    }
}
