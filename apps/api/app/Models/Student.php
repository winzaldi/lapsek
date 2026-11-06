<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $fillable = ['nisn', 'name', 'birth_date', 'sex', 'is_active'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_active' => 'boolean'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function accountLink(): HasOne
    {
        return $this->hasOne(StudentAccountLink::class);
    }
}
