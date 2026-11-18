<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
