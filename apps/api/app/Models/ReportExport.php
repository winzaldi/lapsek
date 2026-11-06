<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasUuids;

    protected $fillable = [
        'requested_by_user_id',
        'filters',
        'school_ids',
        'format',
        'required_role',
        'status',
        'disk',
        'path',
        'row_count',
        'failure_message',
        'snapshot_at',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'school_ids' => 'array',
            'row_count' => 'integer',
            'snapshot_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
