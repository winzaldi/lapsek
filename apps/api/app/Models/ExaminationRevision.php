<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['examination_id', 'version', 'actor_user_id', 'reason', 'snapshot', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'json', 'created_at' => 'datetime'];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
