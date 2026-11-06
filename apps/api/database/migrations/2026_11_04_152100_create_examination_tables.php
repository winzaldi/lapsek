<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('examiner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('school_name_snapshot');
            $table->string('class_name_snapshot')->nullable();
            $table->date('examined_at');
            $table->string('examiner_name');
            $table->foreignId('entered_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version')->default(0);
            $table->decimal('bmi', 10, 2)->nullable();
            $table->string('bmi_calculation_version', 32)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'semester_id']);
            $table->index(['school_id', 'semester_id', 'status']);
        });

        Schema::create('examination_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_key', 64);
            $table->string('status', 20);
            $table->json('value')->nullable();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['examination_id', 'indicator_key'], 'exam_observation_unique');
        });

        Schema::create('examination_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['examination_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_revisions');
        Schema::dropIfExists('examination_observations');
        Schema::dropIfExists('examinations');
    }
};
