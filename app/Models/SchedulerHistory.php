<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class SchedulerHistory extends Model
{
    use MassPrunable;

    protected $fillable = [
        'command',
        'triggered_by',
        'status',
        'duration_ms',
        'output',
        'ran_at',
    ];

    protected $casts = [
        'ran_at'      => 'datetime',
        'duration_ms' => 'integer',
    ];

    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('ran_at', '<', now()->subDays(30));
    }

    public function isManual(): bool
    {
        return $this->triggered_by === 'manual';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function formattedDuration(): string
    {
        if ($this->duration_ms === null) {
            return '-';
        }

        if ($this->duration_ms < 1000) {
            return "{$this->duration_ms}ms";
        }

        return round($this->duration_ms / 1000, 2) . 's';
    }
}
