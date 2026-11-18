<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['question_snapshot' => 'array', 'student_snapshot' => 'array', 'draft_answers' => 'array', 'score' => 'decimal:2', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function answers()
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function grades()
    {
        return $this->hasMany(GradeRecord::class)->orderByDesc('revision');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_progress' => 'Em andamento','pending_review' => 'Aguardando correção','graded' => 'Corrigida',default => 'Enviada'
        };
    }
}
