<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(string $action, string $target, array $details = []): void
    {
        static::create(['actor_id' => auth()->id(), 'action' => $action, 'target' => $target, 'details' => $details, 'created_at' => now()]);
    }
}
