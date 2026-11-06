<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Examination extends Model
{
    protected $fillable = [
        'student_id',
        'school_id',
        'school_class_id',
        'semester_id',
        'examiner_user_id',
        'school_name_snapshot',
        'class_name_snapshot',
        'examined_at',
        'examiner_name',
        'entered_by_user_id',
        'status',
        'version',
        'bmi',
        'bmi_calculation_version',
        'finalized_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'examined_at' => 'date',
            'bmi' => 'decimal:2',
            'finalized_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_user_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(ExaminationObservation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ExaminationRevision::class)->orderBy('version');
    }
}
