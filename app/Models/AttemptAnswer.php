<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttemptAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['needs_review' => 'boolean', 'points' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }
}
