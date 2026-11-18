<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class GradeRecord extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['answer_snapshot' => 'array', 'grade' => 'decimal:2', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O histórico de notas é imutável. Registre uma nova revisão.'));
        static::deleting(fn () => throw new LogicException('O histórico de notas não pode ser excluído.'));
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
