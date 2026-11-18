<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['passing_grade' => 'decimal:2'];
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class)->orderBy('number');
    }
}
