<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('filters');
            $table->json('school_ids');
            $table->string('format', 8);
            $table->string('required_role', 24);
            $table->string('status', 16)->default('queued');
            $table->string('disk', 32)->default('local');
            $table->string('path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['requested_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
