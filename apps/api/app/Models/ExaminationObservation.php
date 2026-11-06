<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationObservation extends Model
{
    protected $fillable = [
        'examination_id',
        'indicator_key',
        'status',
        'value',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
